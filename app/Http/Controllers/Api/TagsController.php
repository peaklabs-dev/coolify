<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TagsController extends Controller
{
    #[OA\Get(
        summary: 'List',
        description: 'Get all tags for the current team.',
        path: '/tags',
        operationId: 'list-tags',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Tags'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of tags.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'uuid', type: 'string'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'team_id', type: 'integer'),
                                    new OA\Property(property: 'created_at', type: 'string'),
                                    new OA\Property(property: 'updated_at', type: 'string'),
                                ]
                            )
                        )
                    ),
                ]),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
        ]
    )]
    public function index(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $tags = Tag::where('team_id', $teamId)->orderBy('name')->get();

        return response()->json(
            serializeApiResponse($tags)
        );
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get a specific tag by UUID.',
        path: '/tags/{uuid}',
        operationId: 'get-tag',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Tags'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Tag UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tag details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'uuid', type: 'string'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'team_id', type: 'integer'),
                        new OA\Property(property: 'created_at', type: 'string'),
                        new OA\Property(property: 'updated_at', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                description: 'Tag not found.',
            ),
        ]
    )]
    public function show(Request $request, $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $tag = Tag::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->first();

        if (! $tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        return response()->json(
            serializeApiResponse($tag)
        );
    }

    #[OA\Post(
        summary: 'Create',
        description: 'Create a new tag.',
        path: '/tags',
        operationId: 'create-tag',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Tags'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'production'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tag created successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'uuid', type: 'string'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'team_id', type: 'integer'),
                        new OA\Property(property: 'created_at', type: 'string'),
                        new OA\Property(property: 'updated_at', type: 'string'),
                    ]
                )
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
                response: 409,
                description: 'Tag with this name already exists.',
            ),
        ]
    )]
    public function store(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $existingTag = Tag::where('name', strtolower($request->name))
            ->where('team_id', $teamId)
            ->first();

        if ($existingTag) {
            return response()->json([
                'message' => 'A tag with this name already exists for your team.',
            ], 409);
        }

        $tag = Tag::create([
            'name' => strtolower($request->name),
            'team_id' => $teamId,
        ]);

        return response()->json(serializeApiResponse($tag), 201);
    }

    #[OA\Patch(
        summary: 'Update',
        description: 'Update an existing tag.',
        path: '/tags/{uuid}',
        operationId: 'update-tag',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Tags'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Tag UUID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'staging'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tag updated successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'uuid', type: 'string'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'team_id', type: 'integer'),
                        new OA\Property(property: 'created_at', type: 'string'),
                        new OA\Property(property: 'updated_at', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                description: 'Tag not found.',
            ),
            new OA\Response(
                response: 409,
                description: 'Tag with this name already exists.',
            ),
        ]
    )]
    public function update(Request $request, $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $tag = Tag::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->first();

        if (! $tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        $existingTag = Tag::where('name', strtolower($request->name)) // TODO: Remove strtolower and also there is not unique constraint on the name per team in the database
            ->where('team_id', $teamId)
            ->where('id', '!=', $tag->id)
            ->first();

        if ($existingTag) {
            return response()->json([
                'message' => 'Another tag with this name already exists for your team.',
            ], 409);
        }

        $tag->name = strtolower($request->name);
        $tag->save();

        return response()->json(serializeApiResponse($tag));
    }

    #[OA\Delete(
        summary: 'Delete',
        description: 'Delete a tag.',
        path: '/tags/{uuid}',
        operationId: 'delete-tag',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Tags'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Tag UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Tag deleted successfully.',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                description: 'Tag not found.',
            ),
        ]
    )]
    public function destroy(Request $request, $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $tag = Tag::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->first();

        if (! $tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        $tag->delete();

        return response()->json(['message' => 'Tag deleted.']);
    }
}
