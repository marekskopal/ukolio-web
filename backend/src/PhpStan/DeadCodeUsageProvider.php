<?php

declare(strict_types=1);

namespace Ukolio\PhpStan;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\Router\Attribute\Route;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use ReflectionAttribute;
use ReflectionEnumUnitCase;
use ReflectionMethod;
use ReflectionProperty;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

final class DeadCodeUsageProvider extends ReflectionBasedMemberUsageProvider
{
	private const array UsedMethodAttributes = [
		Route::class,
		McpTool::class,
		McpResource::class,
		McpResourceTemplate::class,
		McpPrompt::class,
	];

	private const array OrmPropertyAttributes = [
		Column::class,
		ColumnEnum::class,
		ManyToOne::class,
	];

	protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
	{
		if ($method->isConstructor() && !self::isDto($method->getDeclaringClass()->getName())) {
			return VirtualUsageData::withNote('Autowired by DI container');
		}

		if (self::hasAttribute($method->getAttributes(), self::UsedMethodAttributes)) {
			return VirtualUsageData::withNote('Route or MCP capability');
		}

		if ($method->isPublic() && self::isScriptHostApi($method->getDeclaringClass()->getName())) {
			return VirtualUsageData::withNote('Called from script JavaScript via V8Js');
		}

		return null;
	}

	protected function shouldMarkEnumCaseAsUsed(ReflectionEnumUnitCase $enumCase): ?VirtualUsageData
	{
		if (str_starts_with($enumCase->getDeclaringClass()->getName(), 'Ukolio\\Model\\Entity\\Enum\\')) {
			return VirtualUsageData::withNote('Hydrated from database by ORM');
		}

		return null;
	}

	protected function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
	{
		if (self::hasAttribute($property->getAttributes(), self::OrmPropertyAttributes)) {
			return VirtualUsageData::withNote('Persisted by ORM');
		}

		if ($property->isPublic() && self::isDto($property->getDeclaringClass()->getName())) {
			return VirtualUsageData::withNote('Serialized to JSON');
		}

		if ($property->isPublic() && self::isScriptHostApi($property->getDeclaringClass()->getName())) {
			return VirtualUsageData::withNote('Read from script JavaScript via V8Js');
		}

		return null;
	}

	protected function shouldMarkPropertyAsWritten(ReflectionProperty $property): ?VirtualUsageData
	{
		if (self::hasAttribute($property->getAttributes(), self::OrmPropertyAttributes)) {
			return VirtualUsageData::withNote('Hydrated by ORM');
		}

		return null;
	}

	private static function isDto(string $className): bool
	{
		return str_contains($className, '\\Dto\\');
	}

	private static function isScriptHostApi(string $className): bool
	{
		return str_starts_with($className, 'Ukolio\\Service\\Script\\Host\\');
	}

	/**
	 * @param list<ReflectionAttribute<object>> $attributes
	 * @param list<class-string> $attributeClasses
	 */
	private static function hasAttribute(array $attributes, array $attributeClasses): bool
	{
		foreach ($attributes as $attribute) {
			foreach ($attributeClasses as $attributeClass) {
				if (is_a($attribute->getName(), $attributeClass, true)) {
					return true;
				}
			}
		}

		return false;
	}
}
