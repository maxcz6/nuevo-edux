<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Usuario;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\GrupoCurso;
use App\Models\PeriodoAcademico;

/**
 * DashboardController — Estadísticas reales para el panel de administración.
 *
 * Todos los conteos están filtrados por la institución del usuario autenticado
 * mediante el InstitutionScope aplicado a cada modelo.
 */
class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $institucionId = $request->user()->institucion_id;

        // Periodo activo
        $periodoActivo = PeriodoAcademico::where('estado', 'ACTIVO')->first();

        $stats = [
            'total_usuarios'    => Usuario::count(),
            'total_docentes'    => Docente::where('estado', 'ACTIVO')->count(),
            'total_estudiantes' => Estudiante::where('estado', 'ACTIVO')->count(),
            'grupos_activos'    => GrupoCurso::where('estado', 'ACTIVO')
                ->when($periodoActivo, fn ($q) => $q->where('periodo_id', $periodoActivo->id))
                ->count(),
            'periodo_activo'    => $periodoActivo ? [
                'id'     => $periodoActivo->id,
                'nombre' => $periodoActivo->nombre,
                'codigo' => $periodoActivo->codigo,
                'estado' => $periodoActivo->estado,
            ] : null,
        ];

        return response()->json(['data' => $stats, 'message' => 'OK']);
    }
}
