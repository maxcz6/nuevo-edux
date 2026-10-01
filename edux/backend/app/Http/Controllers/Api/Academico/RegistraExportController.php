<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\ConvocatoriaAdmision;
use App\Models\Postulante;
use App\Models\Matricula;
use App\Models\PeriodoAcademico;
use App\Models\GrupoCurso;
use App\Models\Calificacion;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistraExportController extends Controller
{
    /**
     * Exporta el padrón oficial de ingresantes en formato REGISTRA / MINEDU (CSV UTF-8 con BOM).
     */
    public function exportarPadronIngresantes(Request $request, $convocatoriaId)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($convocatoriaId);

        $postulantes = Postulante::with(['programa'])
            ->where('convocatoria_id', $convocatoria->id)
            ->whereIn('condicion', ['ALCANZO_VACANTE', 'MATRICULADO'])
            ->orderBy('programa_id')
            ->orderBy('orden_merito', 'asc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"REGISTRA_PADRON_INGRESANTES_{$convocatoria->codigo}.csv\"",
        ];

        return new StreamedResponse(function () use ($postulantes, $convocatoria) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'CONVOCATORIA',
                'CODIGO_POSTULANTE',
                'PROGRAMA_ESTUDIOS',
                'TIPO_DOC',
                'NUMERO_DOC',
                'APELLIDO_PATERNO',
                'APELLIDO_MATERNO',
                'NOMBRES',
                'TURNO',
                'MODALIDAD',
                'PUNTAJE_EXAMEN',
                'ORDEN_MERITO',
                'CONDICION'
            ], ';');

            foreach ($postulantes as $p) {
                fputcsv($handle, [
                    $convocatoria->codigo,
                    $p->codigo_postulante,
                    $p->programa?->nombre ?? '',
                    $p->documento_tipo,
                    $p->documento_numero,
                    $p->apellido_paterno,
                    $p->apellido_materno,
                    $p->nombres,
                    $p->turno,
                    $p->modalidad,
                    number_format($p->puntaje_examen, 2, '.', ''),
                    $p->orden_merito,
                    $p->condicion,
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Exporta la nómina de matrícula por periodo y programa oficial.
     */
    public function exportarNominaMatricula(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'periodo_id' => 'required|exists:periodos_academicos,id',
            'programa_id' => 'nullable|exists:unidades_academicas,id',
        ]);

        $periodo = PeriodoAcademico::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($request->periodo_id);

        $query = Matricula::with(['estudiante.usuario', 'estudiante.programa', 'grupoCurso.curso'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->where('estado', 'MATRICULADO')
            ->whereHas('grupoCurso', function ($q) use ($periodo) {
                $q->where('periodo_id', $periodo->id);
            });

        if ($request->filled('programa_id')) {
            $query->whereHas('estudiante', function ($q) use ($request) {
                $q->where('unidad_academica_id', $request->programa_id);
            });
        }

        $matriculas = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"REGISTRA_NOMINA_MATRICULA_{$periodo->codigo}.csv\"",
        ];

        return new StreamedResponse(function () use ($matriculas, $periodo) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'PERIODO',
                'CODIGO_ESTUDIANTE',
                'TIPO_DOC',
                'NUMERO_DOC',
                'APELLIDOS_Y_NOMBRES',
                'PROGRAMA_ESTUDIOS',
                'CODIGO_UD',
                'UNIDAD_DIDACTICA',
                'CREDITOS',
                'FECHA_MATRICULA',
                'ESTADO'
            ], ';');

            foreach ($matriculas as $m) {
                $u = $m->estudiante?->usuario;
                $c = $m->grupoCurso?->curso;
                $apellidosNombres = $u ? "{$u->apellido_paterno} {$u->apellido_materno}, {$u->nombres}" : '';

                fputcsv($handle, [
                    $periodo->codigo,
                    $m->estudiante?->codigo_estudiante ?? '',
                    $u?->documento_tipo ?? 'DNI',
                    $u?->documento_numero ?? '',
                    $apellidosNombres,
                    $m->estudiante?->programa?->nombre ?? '',
                    $c?->codigo ?? '',
                    $c?->nombre ?? '',
                    $c?->creditos ?? 0,
                    $m->fecha_matricula,
                    $m->estado,
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Exporta el acta consolidada oficial de una Unidad Didáctica en escala vigesimal MINEDU.
     */
    public function exportarActaConsolidadaUd(Request $request, $grupoId)
    {
        $user = auth()->user();
        $grupo = GrupoCurso::with(['curso', 'docente.usuario', 'periodo'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($grupoId);

$matriculas = Matricula::with(['estudiante.usuario'])
            ->where('grupo_curso_id', $grupo->id)
            ->whereIn('estado', ['MATRICULADO', 'FINALIZADO'])
            ->get();

        $codigoGrupo = $grupo->codigo_grupo ?? ('G' . $grupo->id);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"REGISTRA_ACTA_CONSOLIDADA_{$codigoGrupo}.csv\"",
        ];

        $nombreInstitucion = $grupo->institucion?->nombre ?? ($user->institucion?->nombre ?? 'INSTITUCION EDUCATIVA');

        return new StreamedResponse(function () use ($grupo, $matriculas, $codigoGrupo, $nombreInstitucion) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['INSTITUTO:', $nombreInstitucion], ';');
            fputcsv($handle, ['UNIDAD DIDACTICA:', $grupo->curso?->nombre ?? '', 'CODIGO:', $grupo->curso?->codigo ?? ''], ';');
            fputcsv($handle, ['PERIODO:', $grupo->periodo?->codigo ?? '', 'SECCION/GRUPO:', $codigoGrupo], ';');
            fputcsv($handle, ['DOCENTE:', $grupo->docente?->usuario ? ($grupo->docente->usuario->apellido_paterno . ' ' . $grupo->docente->usuario->nombres) : ''], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, [
                'NRO',
                'CODIGO_ESTUDIANTE',
                'NUMERO_DOC',
                'ESTUDIANTE',
                'PROMEDIO_FINAL',
                'ESTADO_MINEDU',
                'OBSERVACIONES'
            ], ';');

            $i = 1;
            foreach ($matriculas as $m) {
                $u = $m->estudiante?->usuario;
                $nombre = $u ? "{$u->apellido_paterno} {$u->apellido_materno}, {$u->nombres}" : '';

                $califs = Calificacion::where('grupo_curso_id', $grupo->id)
                    ->where('estudiante_id', $m->estudiante_id)
                    ->get();

                $promedio = 0;
                if ($califs->count() > 0) {
                    $promedio = round($califs->avg('nota'), 0);
                }

                $estadoMinedu = 'DESAPROBADO';
                $obs = '';
                if ($promedio >= 13) {
                    $estadoMinedu = 'APROBADO';
                } elseif ($promedio >= 10) {
                    $estadoMinedu = 'RECUPERACION';
                    $obs = 'Derecho a evaluación de recuperación';
                }

                fputcsv($handle, [
                    $i++,
                    $m->estudiante?->codigo_estudiante ?? '',
                    $u?->documento_numero ?? '',
                    $nombre,
                    $promedio,
                    $estadoMinedu,
                    $obs
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
