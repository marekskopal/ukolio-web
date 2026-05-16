import {provideHttpClient, withInterceptors} from '@angular/common/http';
import {enableProdMode, provideZonelessChangeDetection} from '@angular/core';
import {bootstrapApplication} from '@angular/platform-browser';
import {provideRouter} from '@angular/router';
import {provideMarkdown} from 'ngx-markdown';
import {AppComponent} from '@app/app.component';
import {appRoutes} from '@app/app-routes';
import {errorInterceptor} from '@app/core/interceptors/error.interceptor';
import {jwtInterceptor} from '@app/core/interceptors/jwt.interceptor';
import {environment} from '@environments/environment';

if (environment.production) {
    enableProdMode();
}

bootstrapApplication(AppComponent, {
    providers: [
        provideRouter(appRoutes),
        provideHttpClient(withInterceptors([jwtInterceptor, errorInterceptor])),
        provideMarkdown(),
        provideZonelessChangeDetection(),
    ],
}).catch((err: unknown) => console.error(err));
