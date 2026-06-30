<?php

namespace App\Modules\Diaspora\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Diaspora\Http\Requests\AssignAgentRequest;
use App\Modules\Diaspora\Http\Resources\DiasporaProjectResource;
use App\Modules\Diaspora\Models\DiasporaProject;
use App\Modules\Diaspora\Services\AgentAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Affectation d'un agent dédié à un projet diaspora (back-office, phase B8.2).
 */
class DiasporaAssignmentController extends Controller
{
    /**
     * Affecte un agent (explicite ou automatique). PATCH /api/v1/diaspora-projects/{project}/assign
     */
    public function assign(
        AssignAgentRequest $request,
        DiasporaProject $project,
        AgentAssignmentService $assignment
    ): JsonResponse {
        Gate::authorize('assign', $project);

        $data = $request->validated();

        // Ajuste éventuellement la priorité du dossier (back-office).
        if (! empty($data['priority'])) {
            $project->update(['priority' => $data['priority']]);
        }

        $agent = isset($data['agent_id']) ? User::find($data['agent_id']) : null;
        $project = $assignment->assign($project, $agent);

        return ApiResponse::success(['project' => DiasporaProjectResource::make($project)]);
    }
}
