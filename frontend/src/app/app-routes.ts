import {Routes} from '@angular/router';
import {AuthGuard} from '@app/core/guards/auth.guard';

export const appRoutes: Routes = [
    {
        path: 'login',
        loadComponent: () => import('@app/authentication/login.component').then((m) => m.LoginComponent),
    },
    {
        path: 'sign-up',
        loadComponent: () => import('@app/authentication/sign-up.component').then((m) => m.SignUpComponent),
    },
    {
        path: '',
        canActivate: [AuthGuard],
        loadComponent: () => import('@app/shared/components/layout/layout.component').then((m) => m.LayoutComponent),
        children: [
            {path: '', redirectTo: 'projects', pathMatch: 'full'},
            {
                path: 'projects',
                loadComponent: () => import('@app/projects/projects.component').then((m) => m.ProjectsComponent),
            },
            {
                path: 'projects/new',
                loadComponent: () => import('@app/projects/add-edit-project.component').then((m) => m.AddEditProjectComponent),
            },
            {
                path: 'projects/:id/edit',
                loadComponent: () => import('@app/projects/add-edit-project.component').then((m) => m.AddEditProjectComponent),
            },
            {
                path: 'projects/:id/board',
                loadComponent: () => import('@app/board/board.component').then((m) => m.BoardComponent),
            },
            {
                path: 'projects/:id/workflow',
                loadComponent: () => import('@app/workflow-editor/workflow-editor.component').then((m) => m.WorkflowEditorComponent),
            },
            {
                path: 'projects/:id/events',
                loadComponent: () => import('@app/events/events.component').then((m) => m.EventsComponent),
            },
        ],
    },
    {path: '**', redirectTo: ''},
];
