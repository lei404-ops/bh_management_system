<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\MaintenanceService;
use App\Services\UploadService;
use App\Support\ApiResponse;
use App\Support\Validator;
use Illuminate\Http\Request;

/**
 * Maintenance API
 *   GET  /api/maintenance[?status=]            tickets visible to the current role
 *   GET  /api/maintenance?id=5&log=1           timeline of a ticket
 *   GET  /api/maintenance?staff=1              staff list (admin/landlord, for assignment)
 *   POST /api/maintenance action=create        tenant (multipart form, optional photo)
 *   POST /api/maintenance action=status        id, status, note
 *   POST /api/maintenance action=assign        admin/landlord: id, staff_id
 */
class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->asArray();

        if ($request->query->has('staff')) {
            if (!in_array($user['role'], ['admin', 'landlord'], true)) {
                return ApiResponse::error('You do not have permission to do that.', 403);
            }
            return ApiResponse::ok(MaintenanceService::staffList());
        }
        if ($request->query->has('log')) {
            $ticket = MaintenanceService::find((int) $request->query('id', 0));
            if (!$ticket || !MaintenanceService::canView($user, $ticket)) {
                return ApiResponse::error('Ticket not found.', 404);
            }
            return ApiResponse::ok(MaintenanceService::logFor((int) $ticket['id']));
        }
        return ApiResponse::ok(MaintenanceService::listFor($user, ['status' => (string) $request->query('status', '')]));
    }

    public function store(Request $request)
    {
        $user = $request->user()->asArray();

        switch ((string) $request->input('action', '')) {
            case 'create':
                return $this->create($request, $user);

            case 'status':
                $v = new Validator($request->all());
                $v->positiveInt('id', 'Ticket')->inList('status', MaintenanceService::STATUSES, 'Status')
                  ->maxLength('note', 500, 'Note');
                if ($v->fails()) {
                    return ApiResponse::error('Invalid request.', 422, $v->errors());
                }
                MaintenanceService::changeStatus((int) $v->get('id'), $v->get('status'), $user, $v->get('note'));
                return ApiResponse::ok(null, 'Ticket updated.');

            case 'assign':
                $v = new Validator($request->all());
                $v->positiveInt('id', 'Ticket')->positiveInt('staff_id', 'Staff member');
                if ($v->fails()) {
                    return ApiResponse::error('Invalid request.', 422, $v->errors());
                }
                MaintenanceService::assign((int) $v->get('id'), (int) $v->get('staff_id'), $user);
                return ApiResponse::ok(null, 'Ticket assigned.');

            default:
                return ApiResponse::error('Unknown action.', 400);
        }
    }

    private function create(Request $request, array $user)
    {
        if ($user['role'] !== 'tenant') {
            return ApiResponse::error('Only tenants can submit maintenance requests.', 403);
        }
        $tenantId = AuthService::tenantIdFor($user['id']);
        if ($tenantId === null) {
            return ApiResponse::error('Tenant profile not found.', 404);
        }

        $v = new Validator($request->all());
        $v->required('title', 'Title')->maxLength('title', 150, 'Title')
          ->inList('category', MaintenanceService::CATEGORIES, 'Category')
          ->inList('priority', MaintenanceService::PRIORITIES, 'Priority')
          ->required('description', 'Description')->minLength('description', 10, 'Description')
          ->maxLength('description', 2000, 'Description');
        if ($v->fails()) {
            return ApiResponse::error('Please fix the highlighted fields.', 422, $v->errors());
        }

        $photo = UploadService::image($request->file('photo'), 'maintenance');   // optional

        $ticket = MaintenanceService::create($tenantId, [
            'title'       => $v->get('title'),
            'category'    => $v->get('category'),
            'priority'    => $v->get('priority'),
            'description' => $v->get('description'),
        ], $photo, $user['id']);

        return ApiResponse::ok($ticket, 'Request submitted. Your ticket number is ' . $ticket['ticket_no'] . '.');
    }
}
