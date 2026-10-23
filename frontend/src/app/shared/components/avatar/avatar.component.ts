import {ChangeDetectionStrategy, Component, computed, input, signal} from '@angular/core';
import {initials} from '@app/shared/initials';

// Renders the user's uploaded avatar, falling back to initials when there is none or it fails to load.
// The host is `display: contents`: size, shape and the initials' colors come from the wrapping element.
@Component({
    selector: 'uk-avatar',
    standalone: true,
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: `
        @if (showImage()) {
            <!-- Plain <img>, not NgOptimizedImage: a 256px avatar shown at ~22px would trip its oversized-image warning. -->
            <!-- eslint-disable-next-line @angular-eslint/template/prefer-ngsrc -->
            <img class="uk-avatar-img" [src]="avatarUrl()" alt="" (error)="failedUrl.set(avatarUrl())" />
        } @else {
            {{ letters() }}
        }
    `,
    styles: `
        :host {
            display: contents;
            /* Pass the wrapper's shape through to the image. */
            border-radius: inherit;
        }

        .uk-avatar-img {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: inherit;
            object-fit: cover;
        }
    `,
})
export class AvatarComponent {
    public readonly name = input.required<string>();
    public readonly avatarUrl = input<string | null | undefined>(null);

    protected readonly failedUrl = signal<string | null | undefined>(null);

    protected readonly showImage = computed<boolean>(() => {
        const url = this.avatarUrl();
        return !!url && url !== this.failedUrl();
    });

    protected readonly letters = computed<string>(() => initials(this.name()));
}
