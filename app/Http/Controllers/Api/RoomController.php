<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RoomService;
use App\Support\ApiResponse;
use App\Support\Db;
use App\Support\Validator;
use Illuminate\Http\Request;

/**
 * Rooms API
 *   GET  /api/rooms?type=&status=&q=&page=      list (any logged-in role)
 *   POST /api/rooms  action=create              admin/landlord
 *   POST /api/rooms  action=status&id=&status=  admin/landlord
 */
class RoomController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->asArray();

        $result = RoomService::search([
            'type'     => (string) $request->query('type', ''),
            'status'   => (string) $request->query('status', ''),
            'q'        => trim((string) $request->query('q', '')),
            'page'     => (int) $request->query('page', 1),
            'per_page' => (int) $request->query('per_page', 10),
        ]);

        // Privacy: only managers may see who lives in a room.
        if (!in_array($user['role'], ['admin', 'landlord'], true)) {
            foreach ($result['items'] as &$room) {
                unset($room['occupants']);
            }
            unset($room);
        }
        return ApiResponse::ok($result);
    }

    public function store(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'landlord'], true)) {
            return ApiResponse::error('You do not have permission to do that.', 403);
        }

        $action = (string) $request->input('action', '');

        if ($action === 'create') {
            $v = new Validator($request->all());
            $v->required('room_number', 'Room number')->maxLength('room_number', 20, 'Room number')
              ->inList('room_type', RoomService::TYPES, 'Room type')
              ->money('monthly_rate', 'Monthly rate')
              ->positiveInt('capacity', 'Capacity')
              ->positiveInt('floor_no', 'Floor')
              ->maxLength('description', 255, 'Description');
            if ($v->fails()) {
                return ApiResponse::error('Please fix the highlighted fields.', 422, $v->errors());
            }
            if (Db::fetchValue('SELECT id FROM rooms WHERE room_number = ?', [$v->get('room_number')])) {
                return ApiResponse::error('That room number already exists.', 422, ['room_number' => 'Already in use.']);
            }
            $id = RoomService::create([
                'room_number'  => $v->get('room_number'),
                'room_type'    => $v->get('room_type'),
                'monthly_rate' => (float) $v->get('monthly_rate'),
                'capacity'     => (int) $v->get('capacity'),
                'floor_no'     => (int) $v->get('floor_no'),
                'description'  => $v->get('description'),
            ]);
            return ApiResponse::ok(['id' => $id], 'Room created.');
        }

        if ($action === 'status') {
            $v = new Validator($request->all());
            $v->positiveInt('id', 'Room')->inList('status', RoomService::STATUSES, 'Status');
            if ($v->fails()) {
                return ApiResponse::error('Invalid request.', 422, $v->errors());
            }
            RoomService::setStatus((int) $v->get('id'), $v->get('status'));
            return ApiResponse::ok(null, 'Room status updated.');
        }

        return ApiResponse::error('Unknown action.', 400);
    }
}
