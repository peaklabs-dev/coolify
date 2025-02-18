<?php

use App\Enums\BuildPackTypes;
use App\Enums\RedirectTypes;
use App\Enums\StaticImageTypes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;

function getTeamIdFromToken()
{
    $token = auth()->user()->currentAccessToken();

    return data_get($token, 'team_id');
}
function invalidTokenResponse()
{
    return response()->json(['message' => 'Invalid token.', 'docs' => 'https://coolify.io/docs/api-reference/authorization'], 400);
}

function serializeApiResponse($data)
{
    $orderFields = function ($item) use (&$orderFields) {
        $d = collect($item);

        foreach ($d as $key => $value) {
            if (is_array($value) || $value instanceof Collection || $value instanceof SupportCollection) {
                $d[$key] = collect($value)->map(fn ($i) => $orderFields($i));
            }
        }

        $ordered = collect();

        $ordered['id'] = $d['id'] ?? null;
        $ordered['uuid'] = $d['uuid'] ?? null;
        $ordered['name'] = $d['name'] ?? null;
        $ordered['description'] = $d['description'] ?? null;
        $ordered['team_id'] = $d['team_id'] ?? null;
        $ordered['project_id'] = $d['project_id'] ?? null;
        $ordered['created_at'] = $d['created_at'] ?? null;
        $ordered['updated_at'] = $d['updated_at'] ?? null;

        foreach ($d as $key => $value) {
            if (! in_array($key, ['id', 'uuid', 'name', 'description', 'team_id', 'project_id', 'created_at', 'updated_at'])) {
                $ordered[$key] = $value;
            }
        }

        return $ordered;
    };

    if ($data instanceof Collection || is_array($data)) {
        return collect($data)->map(fn ($item) => $orderFields($item));
    }

    return $orderFields($data);
}

function sharedDataApplications()
{
    return [
        'git_repository' => 'string',
        'git_branch' => 'string',
        'build_pack' => Rule::enum(BuildPackTypes::class),
        'is_static' => 'boolean',
        'static_image' => Rule::enum(StaticImageTypes::class),
        'domains' => 'string',
        'redirect' => Rule::enum(RedirectTypes::class),
        'git_commit_sha' => 'string',
        'docker_registry_image_name' => 'string|nullable',
        'docker_registry_image_tag' => 'string|nullable',
        'install_command' => 'string|nullable',
        'build_command' => 'string|nullable',
        'start_command' => 'string|nullable',
        'ports_exposes' => 'string|regex:/^(\d+)(,\d+)*$/',
        'ports_mappings' => 'string|regex:/^(\d+:\d+)(,\d+:\d+)*$/|nullable',
        'base_directory' => 'string|nullable',
        'publish_directory' => 'string|nullable',
        'health_check_enabled' => 'boolean',
        'health_check_path' => 'string',
        'health_check_port' => 'string|nullable',
        'health_check_host' => 'string',
        'health_check_method' => 'string',
        'health_check_return_code' => 'numeric',
        'health_check_scheme' => 'string',
        'health_check_response_text' => 'string|nullable',
        'health_check_interval' => 'numeric',
        'health_check_timeout' => 'numeric',
        'health_check_retries' => 'numeric',
        'health_check_start_period' => 'numeric',
        'limits_memory' => 'string',
        'limits_memory_swap' => 'string',
        'limits_memory_swappiness' => 'numeric',
        'limits_memory_reservation' => 'string',
        'limits_cpus' => 'string',
        'limits_cpuset' => 'string|nullable',
        'limits_cpu_shares' => 'numeric',
        'custom_labels' => 'string|nullable',
        'custom_docker_run_options' => 'string|nullable',
        'post_deployment_command' => 'string|nullable',
        'post_deployment_command_container' => 'string',
        'pre_deployment_command' => 'string|nullable',
        'pre_deployment_command_container' => 'string',
        'manual_webhook_secret_github' => 'string|nullable',
        'manual_webhook_secret_gitlab' => 'string|nullable',
        'manual_webhook_secret_bitbucket' => 'string|nullable',
        'manual_webhook_secret_gitea' => 'string|nullable',
        'docker_compose_location' => 'string',
        'docker_compose' => 'string|nullable',
        'docker_compose_raw' => 'string|nullable',
        'docker_compose_domains' => 'array|nullable',
        'docker_compose_custom_start_command' => 'string|nullable',
        'docker_compose_custom_build_command' => 'string|nullable',
    ];
}

function validateIncomingRequest(Request $request)
{
    // check if request is json
    if (! $request->isJson()) {
        return response()->json([
            'message' => 'Invalid request.',
            'error' => 'Content-Type must be application/json.',
        ], 400);
    }
    // check if request is valid json
    if (! json_decode($request->getContent())) {
        return response()->json([
            'message' => 'Invalid request.',
            'error' => 'Invalid JSON.',
        ], 400);
    }
    // check if valid json is empty
    if (empty($request->json()->all())) {
        return response()->json([
            'message' => 'Invalid request.',
            'error' => 'Empty JSON.',
        ], 400);
    }
}

function removeUnnecessaryFieldsFromRequest(Request $request)
{
    $request->offsetUnset('project_uuid');
    $request->offsetUnset('environment_name');
    $request->offsetUnset('environment_uuid');
    $request->offsetUnset('destination_uuid');
    $request->offsetUnset('server_uuid');
    $request->offsetUnset('type');
    $request->offsetUnset('domains');
    $request->offsetUnset('instant_deploy');
    $request->offsetUnset('github_app_uuid');
    $request->offsetUnset('private_key_uuid');
    $request->offsetUnset('use_build_server');
    $request->offsetUnset('is_static');
}
