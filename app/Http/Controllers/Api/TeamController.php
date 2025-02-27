<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TeamController extends Controller
{
    private function removeSensitiveData($team)
    {
        $team->makeHidden([
            'custom_server_limit',
            'pivot',
        ]);
        if (request()->attributes->get('can_read_sensitive', false) === false) {
            $team->makeHidden([
                'smtp_username',
                'smtp_password',
                'resend_api_key',
                'telegram_token',
            ]);
        }

        return serializeApiResponse($team);
    }

    /**
     * Format members data to include role directly on the member object
     * and remove unnecessary pivot data
     */
    private function formatMembersData($team)
    {
        if (isset($team->members) && count($team->members) > 0) {
            $team->members->transform(function ($member) {
                if (isset($member->pivot) && isset($member->pivot->role)) {
                    $member->role = $member->pivot->role;
                }

                $member->makeHidden(['pivot']);

                return $member;
            });
        }

        return $team;
    }

    #[OA\Get(
        summary: 'List',
        description: 'Get all teams.',
        path: '/teams',
        operationId: 'list-teams',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of teams.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Team')
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
    public function teams(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $teams = request()->user()->teams->sortBy('id');

        foreach ($teams as $team) {
            $team->load('members');
            $this->formatMembersData($team);
        }

        $teams = $teams->map(function ($team) {
            return $this->removeSensitiveData($team);
        });

        return response()->json(
            $teams,
        );
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get team by TeamId.',
        path: '/teams/{id}',
        operationId: 'get-team-by-id',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Team ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of teams.',
                content: new OA\JsonContent(ref: '#/components/schemas/Team')
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
    public function team_by_id(Request $request)
    {
        $id = $request->id;
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $teams = request()->user()->teams;
        $team = $teams->where('id', $id)->first();
        if (is_null($team)) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $team->load('members');
        $this->formatMembersData($team);

        $team = $this->removeSensitiveData($team);

        return response()->json(
            serializeApiResponse($team),
        );
    }

    #[OA\Get(
        summary: 'List Members',
        description: 'Get members by TeamId.',
        path: '/teams/{id}/members',
        operationId: 'get-members-by-team-id',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Team ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of members.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User')
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
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function members_by_id(Request $request)
    {
        $id = $request->id;
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $teams = request()->user()->teams;
        $team = $teams->where('id', $id)->first();
        if (is_null($team)) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $team->load('members');
        $this->formatMembersData($team);

        return response()->json(
            serializeApiResponse($team->members),
        );
    }

    #[OA\Get(
        summary: 'Authenticated Team',
        description: 'Get currently authenticated team.',
        path: '/teams/current',
        operationId: 'get-current-team',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current Team.',
                content: new OA\JsonContent(ref: '#/components/schemas/Team')),
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
    public function current_team(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $team = request()->user()->currentTeam();

        $team->load('members');
        $this->formatMembersData($team);

        return response()->json(
            $this->removeSensitiveData($team),
        );
    }

    #[OA\Get(
        summary: 'Authenticated Team Members',
        description: 'Get currently authenticated team members.',
        path: '/teams/current/members',
        operationId: 'get-current-team-members',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Currently authenticated team members.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User')
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
    public function current_team_members(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $team = request()->user()->currentTeam();
        $team->load('members');
        $this->formatMembersData($team);

        return response()->json(
            serializeApiResponse($team->members),
        );
    }

    #[OA\Post(
        summary: 'Create',
        description: 'Create a new team.',
        path: '/teams',
        operationId: 'create-team',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'My Team'),
                    new OA\Property(property: 'description', type: 'string', example: 'My team description'),
                    new OA\Property(
                        property: 'members',
                        type: 'array',
                        description: 'Array of members to add to the team',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'role', type: 'string', example: 'admin', enum: ['admin', 'member', 'owner']),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Team created successfully.',
                content: new OA\JsonContent(ref: '#/components/schemas/Team')
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
                response: 422,
                description: 'Validation failed.',
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
            'description' => 'nullable|string',
            'members' => 'nullable|array',
            'members.*.user_id' => 'required|integer|exists:users,id',
            'members.*.role' => 'required|string|in:admin,member,owner',
        ]);

        $team = new Team;
        $team->name = $request->name;
        $team->description = $request->description;
        $team->personal_team = false;
        $team->save();

        $ownersInMembers = [];
        if ($request->has('members') && is_array($request->members)) {
            foreach ($request->members as $member) {
                if (isset($member['role']) && $member['role'] === 'owner' && isset($member['user_id'])) {
                    $ownersInMembers[] = $member['user_id'];
                }
            }
        }

        if (count($ownersInMembers) > 1) {
            $team->delete();

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    'members' => ['Only one owner can be assigned to a team.'],
                ],
            ], 422);
        } elseif (count($ownersInMembers) === 1) {
            $ownerId = $ownersInMembers[0];
            $owner = User::find($ownerId);
            if ($owner) {
                $owner->teams()->attach($team, ['role' => 'owner']);

                if ($ownerId !== request()->user()->id) {
                    request()->user()->teams()->attach($team, ['role' => 'admin']);
                }
            }
        } else {
            $ownersInMembers[] = request()->user()->id;
            request()->user()->teams()->attach($team, ['role' => 'owner']);
        }

        if ($request->has('members') && is_array($request->members)) {
            foreach ($request->members as $member) {
                if (isset($member['user_id']) && isset($member['role'])) {
                    $userId = $member['user_id'];
                    $role = $member['role'];

                    if (($role === 'owner' && in_array($userId, $ownersInMembers)) ||
                        (count($ownersInMembers) === 0 && $userId === request()->user()->id)) {
                        continue;
                    }

                    $user = User::find($userId);
                    if ($user && $user->id !== request()->user()->id) {
                        $user->teams()->attach($team, ['role' => $role]);
                    }
                }
            }
        }

        $team->load('members');
        $this->formatMembersData($team);

        return response()->json(
            $this->removeSensitiveData($team),
            201
        );
    }
}
