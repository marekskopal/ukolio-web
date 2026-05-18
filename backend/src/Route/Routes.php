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
	case WorkspaceTransferOwnership = '/api/workspaces/{workspaceId:number}/transfer-ownership';
	case WorkspaceInvitations = '/api/workspaces/{workspaceId:number}/invitations';
	case WorkspaceFields = '/api/workspaces/{workspaceId:number}/fields';
	case WorkspaceField = '/api/workspaces/{workspaceId:number}/fields/{fieldId:number}';
	case WorkspaceMcpClients = '/api/workspaces/{workspaceId:number}/mcp-clients';
	case WorkspaceEvents = '/api/workspaces/{workspaceId:number}/events';
	case WorkspaceAgentStats = '/api/workspaces/{workspaceId:number}/agent-stats';
	case Invitation = '/api/invitations/{invitationId:number}';
	case InvitationLookup = '/api/invitations/lookup';
	case InvitationAccept = '/api/invitations/accept';

	case Projects = '/api/projects';
	case Project = '/api/projects/{projectId:number}';
	case ProjectBoard = '/api/projects/{projectId:number}/board';
	case ProjectEvents = '/api/projects/{projectId:number}/events';
	case ProjectWorkflow = '/api/projects/{projectId:number}/workflow';
	case ProjectTasks = '/api/projects/{projectId:number}/tasks';
	case ProjectFields = '/api/projects/{projectId:number}/fields';

	case Workflows = '/api/workflows';
	case WorkflowStatuses = '/api/workflows/{workflowId:number}/statuses';
	case Status = '/api/statuses/{statusId:number}';
	case StatusMove = '/api/statuses/{statusId:number}/move';

	case Tasks = '/api/tasks';
	case Task = '/api/tasks/{taskId:number}';
	case TaskMove = '/api/tasks/{taskId:number}/move';
	case TaskFiles = '/api/tasks/{taskId:number}/files';
	case TaskFile = '/api/tasks/{taskId:number}/files/{fileId:number}';
	case TaskFileContent = '/api/tasks/{taskId:number}/files/{fileId:number}/content';
	case TaskRelations = '/api/tasks/{taskId:number}/relations';
	case TaskRelation = '/api/task-relations/{relationId:number}';

	case AdminUsers = '/api/admin/users';
	case AdminUser = '/api/admin/users/{userId:number}';
	case AdminWorkspaces = '/api/admin/workspaces';
	case AdminWorkspace = '/api/admin/workspaces/{workspaceId:number}';
	case AdminWorkspaceMembers = '/api/admin/workspaces/{workspaceId:number}/members';
	case AdminWorkspaceMember = '/api/admin/workspaces/{workspaceId:number}/members/{userId:number}';
	case AdminWorkspaceTransferOwnership = '/api/admin/workspaces/{workspaceId:number}/transfer-ownership';

	case Mcp = '/api/mcp';

	case OAuthMetadata = '/.well-known/oauth-authorization-server/api/mcp';
	case OAuthResourceMetadata = '/.well-known/oauth-protected-resource/api/mcp';
	case OAuthAuthorize = '/api/mcp/oauth/authorize';
	case OAuthToken = '/api/mcp/oauth/token';
	case OAuthRegister = '/api/mcp/oauth/register';
	case OAuthClientInfo = '/api/mcp/oauth/client-info';
}
