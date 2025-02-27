<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EnvironmentController extends Controller
{
    #[OA\Get(
        summary: 'List',
        description: 'List all environments in a project.',
        path: '/projects/{uuid}/environments',
        operationId: 'list-environments',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Environments'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of environments',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Environment')
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function list_environments(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $project = Project::whereTeamId($teamId)->whereUuid($request->uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $environments = $project->environments()->get();

        return response()->json(serializeApiResponse($environments));
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get environment by name or UUID.',
        path: '/projects/{uuid}/environments/{environment_name_or_uuid}',
        operationId: 'get-environment-by-name-or-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Environments'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'environment_name_or_uuid', in: 'path', required: true, description: 'Environment name or UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Environment details',
                content: new OA\JsonContent(ref: '#/components/schemas/Environment')
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function environment_details(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        if (! $request->uuid) {
            return response()->json(['message' => 'UUID is required.'], 422);
        }
        if (! $request->environment_name_or_uuid) {
            return response()->json(['message' => 'Environment name or UUID is required.'], 422);
        }
        $project = Project::whereTeamId($teamId)->whereUuid($request->uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }
        $environment = $project->environments()->whereName($request->environment_name_or_uuid)->first();
        if (! $environment) {
            $environment = $project->environments()->whereUuid($request->environment_name_or_uuid)->first();
        }
        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        return response()->json(serializeApiResponse($environment));
    }

    #[OA\Post(
        summary: 'Create',
        description: 'Create environment inside a project.',
        path: '/projects/{uuid}/environments',
        operationId: 'create-environment',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Environments'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Environment details',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'name' => ['type' => 'string', 'description' => 'The name of the environment.'],
                        'description' => ['type' => 'string', 'description' => 'The description of the environment.'],
                    ],
                    required: ['name']
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Environment created.',
                content: new OA\JsonContent(ref: '#/components/schemas/Environment')
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function create_environment(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $validator = customApiValidator($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project = Project::whereTeamId($teamId)->whereUuid($request->uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        try {
            $environment = $project->environments()->create([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return response()->json(serializeApiResponse($environment))->setStatusCode(201);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23505) {
                return response()->json(['message' => 'Environment with this name already exists.'], 400);
            }

            return response()->json(['message' => 'Failed to create environment.', 'error' => $e->getMessage()], 500);
        }
    }

    #[OA\Patch(
        summary: 'Update',
        description: 'Update environment inside a project.',
        path: '/projects/{uuid}/environments/{environment_name_or_uuid}',
        operationId: 'update-environment',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Environments'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'environment_name_or_uuid', in: 'path', required: true, description: 'Environment name or UUID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Environment details',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'name' => ['type' => 'string', 'description' => 'The name of the environment.'],
                        'description' => ['type' => 'string', 'description' => 'The description of the environment.'],
                    ]
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Environment updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/Environment')
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function update_environment(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $validator = customApiValidator($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project = Project::whereTeamId($teamId)->whereUuid($request->uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $environment = $project->environments()->whereName($request->environment_name_or_uuid)->first();
        if (! $environment) {
            $environment = $project->environments()->whereUuid($request->environment_name_or_uuid)->first();
        }
        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        try {
            $environment->update($request->only(['name', 'description']));

            return response()->json(serializeApiResponse($environment));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23505) {
                return response()->json(['message' => 'Environment with this name already exists.'], 400);
            }

            return response()->json(['message' => 'Failed to update environment.', 'error' => $e->getMessage()], 500);
        }
    }

    #[OA\Delete(
        summary: 'Delete',
        description: 'Delete environment from a project.',
        path: '/projects/{uuid}/environments/{environment_name_or_uuid}',
        operationId: 'delete-environment',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Environments'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'environment_name_or_uuid', in: 'path', required: true, description: 'Environment name or UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Environment deleted.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string', 'example' => 'Environment deleted.'],
                            ]
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function delete_environment(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $project = Project::whereTeamId($teamId)->whereUuid($request->uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $environment = $project->environments()->whereName($request->environment_name_or_uuid)->first();
        if (! $environment) {
            $environment = $project->environments()->whereUuid($request->environment_name_or_uuid)->first();
        }
        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        if (! $environment->isEmpty()) {
            return response()->json(['message' => 'Environment has resources, so it cannot be deleted.'], 400);
        }

        $environment->delete();

        return response()->json(['message' => 'Environment deleted.']);
    }
}
