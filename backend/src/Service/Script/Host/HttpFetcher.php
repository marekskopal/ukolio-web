<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use CurlHandle;
use RuntimeException;
use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT_MS;
use const CURLOPT_URL;
use const PHP_URL_HOST;

/**
 * Outbound HTTP for `ukolio.fetch`. Stateless; per-run caps are enforced by the caller via
 * ScriptRunContext before each call. Only http/https are permitted and the timeout is bounded.
 */
final readonly class HttpFetcher
{
	private const int DefaultTimeoutMs = 10000;
	private const int MaxTimeoutMs = 30000;
	private const int MaxBodyBytes = 5242880;

	/**
	 * @param array<string, mixed> $options
	 * @param list<string> $allowedHosts lowercase host patterns; empty means no restriction
	 */
	public function fetch(string $url, array $options, array $allowedHosts = []): HttpResponse
	{
		if (preg_match('#^https?://#i', $url) !== 1) {
			throw new RuntimeException('ukolio.fetch only supports http(s) URLs.');
		}

		$this->assertHostAllowed($url, $allowedHosts);

		$method = strtoupper(JsValue::string($options['method'] ?? null) ?? 'GET');
		$timeoutMs = min(max(JsValue::int($options['timeoutMs'] ?? null) ?? self::DefaultTimeoutMs, 1), self::MaxTimeoutMs);

		$handle = curl_init();
		if ($handle === false) {
			throw new RuntimeException('ukolio.fetch could not initialise an HTTP client.');
		}

		$responseHeaders = [];
		curl_setopt_array($handle, [
			CURLOPT_URL => $url,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT_MS => $timeoutMs,
			CURLOPT_HTTPHEADER => $this->buildHeaders(JsValue::toAssoc($options['headers'] ?? null)),
			CURLOPT_HEADERFUNCTION => static function (CurlHandle $_, string $header) use (&$responseHeaders): int {
				$parts = explode(':', $header, 2);
				if (count($parts) === 2) {
					$responseHeaders[trim($parts[0])] = trim($parts[1]);
				}

				return strlen($header);
			},
		]);

		$body = JsValue::string($options['body'] ?? null);
		if ($body !== null && $method !== 'GET' && $method !== 'HEAD') {
			curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
		}

		$result = curl_exec($handle);
		if ($result === false) {
			throw new RuntimeException('ukolio.fetch failed: ' . curl_error($handle));
		}

		$status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

		$text = is_string($result) ? $result : '';
		if (strlen($text) > self::MaxBodyBytes) {
			throw new RuntimeException('ukolio.fetch response exceeded the maximum allowed size.');
		}

		return new HttpResponse($status, $responseHeaders, $text);
	}

	/**
	 * Enforce the optional per-workspace outbound-fetch allowlist. A pattern matches the URL host
	 * exactly, or as a parent domain (`example.com` allows `api.example.com`).
	 *
	 * @param list<string> $allowedHosts
	 */
	private function assertHostAllowed(string $url, array $allowedHosts): void
	{
		if ($allowedHosts === []) {
			return;
		}

		$host = strtolower((string) parse_url($url, PHP_URL_HOST));
		if ($host === '') {
			throw new RuntimeException('ukolio.fetch could not determine the request host.');
		}

		foreach ($allowedHosts as $pattern) {
			if ($host === $pattern || str_ends_with($host, '.' . $pattern)) {
				return;
			}
		}

		throw new RuntimeException(sprintf('ukolio.fetch host "%s" is not in this workspace\'s allowlist.', $host));
	}

	/**
	 * @param array<string, mixed> $headers
	 * @return list<string>
	 */
	private function buildHeaders(array $headers): array
	{
		$out = [];
		foreach ($headers as $name => $value) {
			$stringValue = JsValue::string($value);
			if ($stringValue !== null) {
				$out[] = $name . ': ' . $stringValue;
			}
		}

		return $out;
	}
}
