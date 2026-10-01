<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\GrupoCurso;
use App\Models\PeriodoAcademico;
use App\Models\UnidadAcademica;

/**
 * DashboardController — Estadísticas para el panel de administración y coordinación.
 *
 * Filtra automáticamente por institución y, en caso de COORDINADOR, por programa de estudios.
 */
class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        $isCoordinador = $user->hasRole('COORDINADOR') && $user->unidad_academica_id;

        // Periodo activo
        $periodoActivo = PeriodoAcademico::where('estado', 'ACTIVO')->first();

        // Consultas base
        $docentesQuery = Docente::where('estado', 'ACTIVO');
        $estudiantesQuery = Estudiante::where('estado', 'ACTIVO');
        $gruposQuery = GrupoCurso::where('estado', 'ACTIVO')
            ->when($periodoActivo, fn ($q) => $q->where('periodo_id', $periodoActivo->id));

        // Filtro específico para COORDINADOR
        if ($isCoordinador) {
            $docentesQuery->where('unidad_academica_id', $user->unidad_academica_id);
            $estudiantesQuery->where('unidad_academica_id', $user->unidad_academica_id);
            $gruposQuery->whereHas('curso', fn ($q) => $q->where('unidad_academica_id', $user->unidad_academica_id));
        }

        $programa = null;
        if ($isCoordinador) {
            $programa = UnidadAcademica::find($user->unidad_academica_id);
        }

        $stats = [
            'total_usuarios'    => $isCoordinador ? ($docentesQuery->count() + $estudiantesQuery->count()) : Usuario::count(),
            'total_docentes'    => $docentesQuery->count(),
            'total_estudiantes' => $estudiantesQuery->count(),
            'grupos_activos'    => $gruposQuery->count(),
            'periodo_activo'    => $periodoActivo ? [
                'id'     => $periodoActivo->id,
                'nombre' => $periodoActivo->nombre,
                'codigo' => $periodoActivo->codigo,
                'estado' => $periodoActivo->estado,
            ] : null,
            'programa'          => $programa ? [
                'id'     => $programa->id,
                'nombre' => $programa->nombre,
                'codigo' => $programa->codigo,
            ] : null,
        ];

        return response()->json(['data' => $stats, 'message' => 'OK']);
    }
}
