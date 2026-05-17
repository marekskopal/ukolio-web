<?php

declare(strict_types=1);

namespace Ukolio\Route;

enum Routes: string
{
	case Health = '/api/health';

	case AuthenticationLogin = '/api/authentication/login';
	case AuthenticationSignUp = '/api/authentication/sign-up';
	case AuthenticationRefreshToken = '/api/authentication/refresh-token';

	case CurrentUser = '/api/current-user';

	case Workspaces = '/api/workspaces';
	case Workspace = '/api/workspaces/{workspaceId:number}';
	case WorkspaceSwitch = '/api/workspaces/{workspaceId:number}/switch';
	case WorkspaceMembers = '/api/workspaces/{workspaceId:number}/members';
	case WorkspaceMember = '/api/workspaces/{workspaceId:number}/members/{userId:number}';
	case WorkspaceInvitations = '/api/workspaces/{workspaceId:number}/invitations';
	case Invitation = '/api/invitations/{invitationId:number}';
	case InvitationLookup = '/api/invitations/lookup';
	case InvitationAccept = '/api/invitations/accept';

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

	case Mcp = '/api/mcp';

	case OAuthMetadata = '/.well-known/oauth-authorization-server/api/mcp';
	case OAuthResourceMetadata = '/.well-known/oauth-protected-resource/api/mcp';
	case OAuthAuthorize = '/api/mcp/oauth/authorize';
	case OAuthToken = '/api/mcp/oauth/token';
	case OAuthRegister = '/api/mcp/oauth/register';
	case OAuthClientInfo = '/api/mcp/oauth/client-info';
}
