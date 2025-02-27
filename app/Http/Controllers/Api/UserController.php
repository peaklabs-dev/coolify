<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        summary: 'List',
        description: 'List all users in the team.',
        path: '/users',
        operationId: 'list-users',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get all users in the team.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User')
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
        ]
    )]
    public function index(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $users = User::with(['teams' => function ($query) {
            $query->select('teams.id', 'team_user.role');
        }])
            ->whereHas('teams', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->select('id', 'name', 'email', 'created_at', 'updated_at')
            ->get();

        $users->transform(function ($user) {
            $teamsArray = $user->teams->map(function ($team) {
                return [
                    'id' => $team->id,
                    'role' => $team->pivot->role,
                ];
            })->toArray();

            $user->teams_array = $teamsArray;
            unset($user->teams);

            return $user;
        });

        return response()->json(serializeApiResponse($users));
    }

    #[OA\Post(
        summary: 'Create',
        description: 'Create a new user in the team.',
        path: '/users',
        operationId: 'create-user',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'User data',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'name' => ['type' => 'string', 'description' => 'The name of the user.'],
                        'email' => ['type' => 'string', 'description' => 'The email of the user.'],
                        'password' => ['type' => 'string', 'description' => 'The password for the user.'],
                        'role' => ['type' => 'string', 'description' => 'The role of the user in the team.'],
                        'teams' => ['type' => 'array', 'description' => 'Array of team IDs and roles to attach to the user.'],
                    ],
                    required: ['name', 'email', 'password']
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'uuid' => ['type' => 'string', 'example' => 'og888os', 'description' => 'The UUID of the user.'],
                                'name' => ['type' => 'string', 'example' => 'John Doe'],
                                'email' => ['type' => 'string', 'example' => 'john@example.com'],
                                'teams' => ['type' => 'array', 'description' => 'Array of team IDs and roles attached to the user.'],
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
                response: 422,
                description: 'Validation failed.',
            ),
        ]
    )]
    public function store(Request $request)
    {
        $allowedFields = ['name', 'email', 'password', 'role', 'teams'];

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
            'email' => 'email|required|unique:users,email',
            'password' => 'string|min:8|required',
            'role' => 'string|nullable|in:admin,member,owner',
            'teams' => 'array|nullable',
            'teams.*.id' => 'integer|exists:teams,id',
            'teams.*.role' => 'string|in:admin,member,owner',
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

        $teamsWithOwnerRole = [];

        if ($request->has('teams') && is_array($request->teams)) {
            foreach ($request->teams as $index => $team) {
                if (isset($team['id']) && isset($team['role']) && $team['role'] === 'owner') {
                    if (in_array($team['id'], $teamsWithOwnerRole)) {
                        return response()->json([
                            'message' => 'Validation failed.',
                            'errors' => [
                                'teams' => ['A team cannot have multiple owners.'],
                            ],
                        ], 422);
                    }
                    $teamsWithOwnerRole[] = $team['id'];
                }
            }
        } elseif ($request->role === 'owner') {
            $team = Team::find($teamId);
            if ($team) {
                $existingOwner = $team->members()->wherePivot('role', 'owner')->exists();

                if ($existingOwner) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => [
                            'role' => ['This team already has an owner.'],
                        ],
                    ], 422);
                }
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $teamsToAttach = [];

        if ($request->has('teams') && is_array($request->teams)) {
            foreach ($request->teams as $teamData) {
                if (isset($teamData['id'])) {
                    if (isset($teamData['role']) && $teamData['role'] === 'owner') {
                        $team = Team::find($teamData['id']);
                        if ($team) {
                            $existingOwner = $team->members()->wherePivot('role', 'owner')->exists();

                            if ($existingOwner) {
                                $user->delete();

                                return response()->json([
                                    'message' => 'Validation failed.',
                                    'errors' => [
                                        'teams' => ["Team ID {$teamData['id']} already has an owner."],
                                    ],
                                ], 422);
                            }
                        }
                    }

                    $teamsToAttach[$teamData['id']] = ['role' => $teamData['role'] ?? 'member'];
                }
            }
        } else {
            $teamsToAttach[$teamId] = ['role' => $request->role ?? 'member'];
        }

        $user->teams()->attach($teamsToAttach);

        $user->load('teams');

        $attachedTeams = $user->teams->map(function ($team) {
            return [
                'id' => $team->id,
                'role' => $team->pivot->role,
            ];
        })->toArray();

        return response()->json([
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'teams' => $attachedTeams,
        ])->setStatusCode(201);
    }
}
