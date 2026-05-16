<?php

declare(strict_types=1);

namespace TaskManager\Route;

enum Routes: string
{
    case Health = '/api/health';

    case AuthenticationLogin = '/api/authentication/login';
    case AuthenticationSignUp = '/api/authentication/sign-up';
    case AuthenticationRefreshToken = '/api/authentication/refresh-token';

    case CurrentUser = '/api/current-user';

    case Projects = '/api/projects';
    case Project = '/api/projects/{projectId:number}';
    case ProjectBoard = '/api/projects/{projectId:number}/board';
    case ProjectEvents = '/api/projects/{projectId:number}/events';
    case ProjectWorkflow = '/api/projects/{projectId:number}/workflow';
    case ProjectTasks = '/api/projects/{projectId:number}/tasks';

    case WorkflowStatuses = '/api/workflows/{workflowId:number}/statuses';
    case Status = '/api/statuses/{statusId:number}';
    case StatusMove = '/api/statuses/{statusId:number}/move';

    case Task = '/api/tasks/{taskId:number}';
    case TaskMove = '/api/tasks/{taskId:number}/move';
}
