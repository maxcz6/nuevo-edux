<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $query = Notificacion::where('usuario_id', $request->user()->id)
            ->when($request->leido !== null, fn ($q) => $q->where('leido', filter_var($request->leido, FILTER_VALIDATE_BOOLEAN)))
            ->latest('created_at');

        $notificaciones = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $notificaciones->items(),
            'meta' => [
                'current_page' => $notificaciones->currentPage(),
                'last_page'    => $notificaciones->lastPage(),
                'per_page'     => $notificaciones->perPage(),
                'total'        => $notificaciones->total(),
            ],
            'message' => 'OK',
        ]);
    }

    public function marcarLeida(Request $request, int $id)
    {
        $notificacion = Notificacion::where('usuario_id', $request->user()->id)
            ->findOrFail($id);

        $notificacion->update(['leido' => true]);

        return response()->json(['data' => $notificacion, 'message' => 'Notificación marcada como leída.']);
    }

    public function marcarTodasLeidas(Request $request)
    {
        $count = Notificacion::where('usuario_id', $request->user()->id)
            ->where('leido', false)
            ->update(['leido' => true]);

        return response()->json([
            'data'    => ['actualizadas' => $count],
            'message' => 'Todas las notificaciones marcadas como leídas.',
        ]);
    }
}
