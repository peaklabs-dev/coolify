<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    #[OA\Get(
        summary: 'List',
        description: 'List all projects with their environments.',
        path: '/projects',
        operationId: 'list-projects',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get all projects with their environments.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Project')
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function projects(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $projects = Project::whereTeamId($teamId)
            ->select('id', 'uuid', 'name', 'description', 'team_id', 'created_at', 'updated_at')
            ->with('environments')
            ->get();

        return response()->json(serializeApiResponse($projects));
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get project by UUID.',
        path: '/projects/{uuid}',
        operationId: 'get-project-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project details',
                content: new OA\JsonContent(ref: '#/components/schemas/Project')
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
                description: 'Project not found.',
            ),
        ]
    )]
    public function project_by_uuid(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $project = Project::whereTeamId($teamId)
            ->whereUuid(request()->uuid)
            ->select('id', 'uuid', 'name', 'description', 'team_id', 'created_at', 'updated_at')
            ->with('environments')
            ->first();

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        return response()->json(
            serializeApiResponse($project),
        );
    }

    #[OA\Post(
        summary: 'Create',
        description: 'Create project with its environments.',
        path: '/projects',
        operationId: 'create-project',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Project created.',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'name' => ['type' => 'string', 'description' => 'The name of the project.'],
                        'description' => ['type' => 'string', 'description' => 'The description of the project.'],
                        'environments' => [
                            'type' => 'array',
                            'description' => 'Array of environments to create',
                            'items' => new OA\Schema(
                                type: 'object',
                                properties: [
                                    'name' => ['type' => 'string', 'description' => 'The name of the environment.'],
                                    'description' => ['type' => 'string', 'description' => 'The description of the environment.'],
                                ]
                            ),
                        ],
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Project created.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'uuid' => ['type' => 'string', 'example' => 'og888os', 'description' => 'The UUID of the project.'],
                            ]
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function create_project(Request $request)
    {
        $allowedFields = ['name', 'description', 'environments'];

        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }
        $validator = customApiValidator($request->all(), [
            'name' => 'string|max:255|required',
            'description' => 'string|nullable',
            'environments' => 'array|nullable',
            'environments.*.name' => 'string|max:255|required_with:environments',
            'environments.*.description' => 'string|nullable',
        ]);

        $extraFields = array_diff(array_keys($request->all()), $allowedFields);
        if ($validator->fails() || ! empty($extraFields)) {
            $errors = $validator->errors();
            if (! empty($extraFields)) {
                foreach ($extraFields as $field) {
                    $errors->add($field, 'This field is not allowed.');
                }
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description ?? null,
            'team_id' => $teamId,
        ]);

        if ($request->has('environments') && is_array($request->environments)) {
            foreach ($request->environments as $environment) {
                if (is_array($environment) && isset($environment['name']) && ! empty($environment['name'])) {
                    $project->environments()->create([
                        'name' => $environment['name'],
                        'description' => $environment['description'] ?? null,
                    ]);
                }
            }
        }

        return response()->json([
            'uuid' => $project->uuid,
        ])->setStatusCode(201);
    }

    #[OA\Patch(
        summary: 'Update Project',
        description: 'Update Project.',
        path: '/projects/{uuid}',
        operationId: 'update-project-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Project updated.',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'name' => ['type' => 'string', 'description' => 'The name of the project.'],
                        'description' => ['type' => 'string', 'description' => 'The description of the project.'],
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Project updated.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'uuid' => ['type' => 'string', 'example' => 'og888os'],
                                'name' => ['type' => 'string', 'example' => 'Project Name'],
                                'description' => ['type' => 'string', 'example' => 'Project Description'],
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
    public function update_project(Request $request)
    {
        $allowedFields = ['name', 'description'];

        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }
        $validator = customApiValidator($request->all(), [
            'name' => 'string|max:255|nullable',
            'description' => 'string|nullable',
        ]);

        $extraFields = array_diff(array_keys($request->all()), $allowedFields);
        if ($validator->fails() || ! empty($extraFields)) {
            $errors = $validator->errors();
            if (! empty($extraFields)) {
                foreach ($extraFields as $field) {
                    $errors->add($field, 'This field is not allowed.');
                }
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }
        $uuid = $request->uuid;
        if (! $uuid) {
            return response()->json(['message' => 'UUID is required.'], 422);
        }

        $project = Project::whereTeamId($teamId)->whereUuid($uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $project->update($request->only($allowedFields));

        return response()->json([
            'uuid' => $project->uuid,
            'name' => $project->name,
            'description' => $project->description,
        ])->setStatusCode(201);
    }

    #[OA\Delete(
        summary: 'Delete Project',
        description: 'Delete project by UUID.',
        path: '/projects/{uuid}',
        operationId: 'delete-project-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the application.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'uuid',
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project deleted.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string', 'example' => 'Project deleted.'],
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
    public function delete_project(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        if (! $request->uuid) {
            return response()->json(['message' => 'UUID is required.'], 422);
        }
        $project = Project::whereTeamId($teamId)
            ->whereUuid($request->uuid)
            ->with('environments')
            ->first();

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }
        if (! $project->isEmpty()) {
            return response()->json(['message' => 'Project has resources, so it cannot be deleted.'], 400);
        }

        $project->environments()->delete();

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }
}
