<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificadoModular;
use App\Models\Estudiante;
use App\Models\Titulacion;
use App\Models\UnidadAcademica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TitulacionController extends Controller
{
    // ═════════════════════════════════════════════════════════════════════════
    // 1. TITULACIÓN Y LIBRO DE TÍTULOS INSTITUCIONAL (Ley N° 30512)
    // ═════════════════════════════════════════════════════════════════════════

    public function indexTitulaciones(Request $request)
    {
        $user = auth()->user();
        $query = Titulacion::with(['estudiante.usuario', 'programa'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->orderBy('created_at', 'desc');

        if ($request->filled('programa_id')) {
            $query->where('programa_id', $request->input('programa_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('numero_titulo', 'ilike', $term)
                  ->orWhere('numero_registro', 'ilike', $term)
                  ->orWhere('numero_libro', 'ilike', $term)
                  ->orWhere('codigo_verificacion', 'ilike', $term)
                  ->orWhereHas('estudiante.usuario', function ($uq) use ($term) {
                      $uq->where('nombres', 'ilike', $term)
                         ->orWhere('apellido_paterno', 'ilike', $term)
                         ->orWhere('apellido_materno', 'ilike', $term)
                         ->orWhere('documento_numero', 'ilike', $term);
                  });
            });
        }

        return response()->json([
            'data' => $query->paginate(20),
            'message' => 'Titulaciones obtenidas correctamente.'
        ]);
    }

    public function storeTitulacion(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $institucionId = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->integer('institucion_id')
            : $user->institucion_id;

        $validated = $request->validate([
            'institucion_id' => $isSuperAdmin
                ? (($user->institucion_id ? 'nullable' : 'required') . '|integer|exists:instituciones,id')
                : 'prohibited',
            'estudiante_id' => [
                'required', 'integer',
                Rule::exists('estudiantes', 'id')->where('institucion_id', $institucionId),
            ],
            'programa_id' => [
                'required', 'integer',
                Rule::exists('unidades_academicas', 'id')->where('institucion_id', $institucionId),
            ],
            'modalidad_titulacion' => 'required|string|in:PROYECTO_APLICACION_PROFESIONAL,EXAMEN_SUFICIENCIA_PROFESIONAL,TRABAJO_INVESTIGACION',
            'numero_titulo' => 'required|string|max:50',
            'numero_registro' => 'required|string|max:50',
            'numero_folio' => 'required|string|max:30',
            'numero_libro' => 'required|string|max:30',
            'resolucion_directoral' => 'required|string|max:120',
            'codigo_minedu' => 'nullable|string|max:100',
            'fecha_sustentacion' => 'nullable|date',
            'fecha_emision' => 'required|date',
            'estado' => 'nullable|string|in:REGISTRADO,EMITIDO,REPORTADO_MINEDU,ANULADO',
        ]);

        // Verificar que estudiante y programa pertenezcan a la misma institución
        $estudiante = Estudiante::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($validated['estudiante_id']);
        $programa = UnidadAcademica::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($validated['programa_id']);

        // Generar código de verificación único para validación pública / QR
        $institucion = \App\Models\Institucion::find($institucionId);
        $instCode = strtoupper($institucion?->nombre_corto ?: (Str::after($institucion?->codigo ?? '', '-') ?: 'SAM'));
        $codigoVerificacion = 'TIT-' . $instCode . '-' . strtoupper(Str::random(10));

        $titulacion = Titulacion::create([
            'institucion_id' => $institucionId,
            'estudiante_id' => $estudiante->id,
            'programa_id' => $programa->id,
            'modalidad_titulacion' => $validated['modalidad_titulacion'],
            'numero_titulo' => $validated['numero_titulo'],
            'numero_registro' => $validated['numero_registro'],
            'numero_folio' => $validated['numero_folio'],
            'numero_libro' => $validated['numero_libro'],
            'resolucion_directoral' => $validated['resolucion_directoral'],
            'codigo_minedu' => $validated['codigo_minedu'] ?? null,
            'fecha_sustentacion' => $validated['fecha_sustentacion'] ?? null,
            'fecha_emision' => $validated['fecha_emision'],
            'codigo_verificacion' => $codigoVerificacion,
            'estado' => $validated['estado'] ?? 'REGISTRADO',
        ]);

        return response()->json([
            'data' => $titulacion->load(['estudiante.usuario', 'programa']),
            'message' => 'Título registrado en el Libro de Títulos institucional exitosamente.'
        ], 201);
    }

    public function updateTitulacion(Request $request, $id)
    {
        $user = auth()->user();
        $titulacion = Titulacion::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($id);

        $validated = $request->validate([
            'modalidad_titulacion' => 'sometimes|string|in:PROYECTO_APLICACION_PROFESIONAL,EXAMEN_SUFICIENCIA_PROFESIONAL,TRABAJO_INVESTIGACION',
            'numero_titulo' => 'sometimes|string|max:50',
            'numero_registro' => 'sometimes|string|max:50',
            'numero_folio' => 'sometimes|string|max:30',
            'numero_libro' => 'sometimes|string|max:30',
            'resolucion_directoral' => 'sometimes|string|max:120',
            'codigo_minedu' => 'nullable|string|max:100',
            'fecha_sustentacion' => 'nullable|date',
            'fecha_emision' => 'sometimes|date',
            'estado' => 'sometimes|string|in:REGISTRADO,EMITIDO,REPORTADO_MINEDU,ANULADO',
        ]);

        $titulacion->update($validated);

        return response()->json([
            'data' => $titulacion->load(['estudiante.usuario', 'programa']),
            'message' => 'Título actualizado correctamente.'
        ]);
    }

    public function exportLibroTitulosCsv(Request $request)
    {
        $user = auth()->user();
        $query = Titulacion::with(['estudiante.usuario', 'programa'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->orderBy('numero_libro', 'asc')
            ->orderBy('numero_folio', 'asc')
            ->orderBy('numero_registro', 'asc');

        if ($request->filled('programa_id')) {
            $query->where('programa_id', $request->input('programa_id'));
        }

        $titulaciones = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="libro_titulos_sam_' . date('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($titulaciones) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados oficiales formato Libro de Títulos
            fputcsv($handle, [
                'N° Libro',
                'N° Folio',
                'N° Registro',
                'N° Título',
                'DNI / Documento',
                'Apellidos',
                'Nombres',
                'Programa de Estudios',
                'Modalidad de Titulación',
                'Resolución Directoral',
                'Fecha Sustentación',
                'Fecha Emisión',
                'Código REGISTRA / MINEDU',
                'Código QR Verificación',
                'Estado'
            ], ';');

            foreach ($titulaciones as $t) {
                $u = $t->estudiante?->usuario;
                fputcsv($handle, [
                    $t->numero_libro,
                    $t->numero_folio,
                    $t->numero_registro,
                    $t->numero_titulo,
                    $u?->documento_numero ?? '',
                    trim(($u?->apellido_paterno ?? '') . ' ' . ($u?->apellido_materno ?? '')),
                    $u?->nombres ?? '',
                    $t->programa?->nombre ?? '',
                    $t->modalidad_titulacion,
                    $t->resolucion_directoral,
                    $t->fecha_sustentacion ? $t->fecha_sustentacion->format('Y-m-d') : '',
                    $t->fecha_emision ? $t->fecha_emision->format('Y-m-d') : '',
                    $t->codigo_minedu ?? 'PENDIENTE_REGISTRA',
                    $t->codigo_verificacion,
                    $t->estado,
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2. CERTIFICACIÓN MODULAR (RVM N° 277-2019-MINEDU)
    // ═════════════════════════════════════════════════════════════════════════

    public function indexCertificados(Request $request)
    {
        $user = auth()->user();
        $query = CertificadoModular::with(['estudiante.usuario', 'programa'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->orderBy('created_at', 'desc');

        if ($request->filled('programa_id')) {
            $query->where('programa_id', $request->input('programa_id'));
        }

        if ($request->filled('numero_modulo')) {
            $query->where('numero_modulo', $request->input('numero_modulo'));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('nombre_modulo', 'ilike', $term)
                  ->orWhere('codigo_verificacion', 'ilike', $term)
                  ->orWhere('resolucion_directoral', 'ilike', $term)
                  ->orWhereHas('estudiante.usuario', function ($uq) use ($term) {
                      $uq->where('nombres', 'ilike', $term)
                         ->orWhere('apellido_paterno', 'ilike', $term)
                         ->orWhere('apellido_materno', 'ilike', $term)
                         ->orWhere('documento_numero', 'ilike', $term);
                  });
            });
        }

        return response()->json([
            'data' => $query->paginate(20),
            'message' => 'Certificados modulares obtenidos correctamente.'
        ]);
    }

    public function storeCertificado(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $institucionId = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->integer('institucion_id')
            : $user->institucion_id;

        $validated = $request->validate([
            'institucion_id' => $isSuperAdmin
                ? (($user->institucion_id ? 'nullable' : 'required') . '|integer|exists:instituciones,id')
                : 'prohibited',
            'estudiante_id' => [
                'required', 'integer',
                Rule::exists('estudiantes', 'id')->where('institucion_id', $institucionId),
            ],
            'programa_id' => [
                'required', 'integer',
                Rule::exists('unidades_academicas', 'id')->where('institucion_id', $institucionId),
            ],
            'numero_modulo' => 'required|integer|between:1,3',
            'nombre_modulo' => 'required|string|max:255',
            'resolucion_directoral' => 'nullable|string|max:100',
            'horas_teoria' => 'required|integer|min:0',
            'horas_practica' => 'required|integer|min:0',
            'creditos' => 'required|numeric|min:0',
            'horas_efsrt' => 'required|integer|min:0',
            'fecha_emision' => 'required|date',
            'estado' => 'nullable|string|in:EMITIDO,ANULADO',
        ]);

        $estudiante = Estudiante::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($validated['estudiante_id']);
        $programa = UnidadAcademica::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($validated['programa_id']);

        $institucion = \App\Models\Institucion::find($institucionId);
        $instCode = strtoupper($institucion?->nombre_corto ?: (Str::after($institucion?->codigo ?? '', '-') ?: 'SAM'));
        $codigoVerificacion = 'MOD-' . $instCode . '-' . strtoupper(Str::random(10));

        $certificado = CertificadoModular::create([
            'institucion_id' => $institucionId,
            'estudiante_id' => $estudiante->id,
            'programa_id' => $programa->id,
            'numero_modulo' => $validated['numero_modulo'],
            'nombre_modulo' => $validated['nombre_modulo'],
            'resolucion_directoral' => $validated['resolucion_directoral'] ?? null,
            'horas_teoria' => $validated['horas_teoria'],
            'horas_practica' => $validated['horas_practica'],
            'creditos' => $validated['creditos'],
            'horas_efsrt' => $validated['horas_efsrt'],
            'codigo_verificacion' => $codigoVerificacion,
            'fecha_emision' => $validated['fecha_emision'],
            'estado' => $validated['estado'] ?? 'EMITIDO',
        ]);

        return response()->json([
            'data' => $certificado->load(['estudiante.usuario', 'programa']),
            'message' => 'Certificado modular emitido exitosamente.'
        ], 201);
    }

    public function updateCertificado(Request $request, $id)
    {
        $user = auth()->user();
        $certificado = CertificadoModular::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($id);

        $validated = $request->validate([
            'numero_modulo' => 'sometimes|integer|between:1,3',
            'nombre_modulo' => 'sometimes|string|max:255',
            'resolucion_directoral' => 'nullable|string|max:100',
            'horas_teoria' => 'sometimes|integer|min:0',
            'horas_practica' => 'sometimes|integer|min:0',
            'creditos' => 'sometimes|numeric|min:0',
            'horas_efsrt' => 'sometimes|integer|min:0',
            'fecha_emision' => 'sometimes|date',
            'estado' => 'sometimes|string|in:EMITIDO,ANULADO',
        ]);

        $certificado->update($validated);

        return response()->json([
            'data' => $certificado->load(['estudiante.usuario', 'programa']),
            'message' => 'Certificado modular actualizado correctamente.'
        ]);
    }

    public function exportCertificadosCsv(Request $request)
    {
        $user = auth()->user();
        $query = CertificadoModular::with(['estudiante.usuario', 'programa'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->orderBy('programa_id', 'asc')
            ->orderBy('numero_modulo', 'asc');

        if ($request->filled('programa_id')) {
            $query->where('programa_id', $request->input('programa_id'));
        }

        $certificados = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="padron_certificados_modulares_sam_' . date('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($certificados) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Programa de Estudios',
                'N° Módulo',
                'Nombre del Módulo Formativo',
                'DNI',
                'Apellidos y Nombres',
                'Horas Teoría',
                'Horas Práctica',
                'Créditos',
                'Horas EFSRT (Prácticas)',
                'Resolución Directoral',
                'Fecha Emisión',
                'Código QR Verificación',
                'Estado'
            ], ';');

            foreach ($certificados as $c) {
                $u = $c->estudiante?->usuario;
                $apellidos = trim(($u?->apellido_paterno ?? '') . ' ' . ($u?->apellido_materno ?? ''));
                $nombreCompleto = trim($apellidos . ', ' . ($u?->nombres ?? ''));
                fputcsv($handle, [
                    $c->programa?->nombre ?? '',
                    'Módulo ' . $c->numero_modulo,
                    $c->nombre_modulo,
                    $u?->documento_numero ?? '',
                    $nombreCompleto,
                    $c->horas_teoria,
                    $c->horas_practica,
                    number_format($c->creditos, 2),
                    $c->horas_efsrt,
                    $c->resolucion_directoral ?? '',
                    $c->fecha_emision ? $c->fecha_emision->format('Y-m-d') : '',
                    $c->codigo_verificacion,
                    $c->estado,
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3. CONSULTA PÚBLICA DE AUTENTICIDAD DE DOCUMENTOS (QR CIUDADANO)
    // ═════════════════════════════════════════════════════════════════════════

    public function verificarDocumentoPublico($codigo)
    {
        $codigo = trim($codigo);

        // 1. Buscar en Titulaciones
        $titulacion = Titulacion::withoutGlobalScopes()
            ->with(['institucion', 'estudiante.usuario', 'programa'])
            ->where('codigo_verificacion', $codigo)
            ->first();

        if ($titulacion) {
            $u = $titulacion->estudiante?->usuario;
            return response()->json([
                'tipo_documento' => 'TITULO_PROFESIONAL_TECNICO',
                'valido' => $titulacion->estado !== 'ANULADO',
                'estado' => $titulacion->estado,
                'institucion' => $titulacion->institucion?->nombre,
                'codigo_modular_institucion' => $titulacion->institucion?->codigo_modular,
                'programa_estudio' => $titulacion->programa?->nombre,
                'titulado' => [
                    'apellidos_nombres' => trim(trim(($u?->apellido_paterno ?? '') . ' ' . ($u?->apellido_materno ?? '')) . ', ' . ($u?->nombres ?? '')),
                    'documento_tipo' => $u?->documento_tipo ?? 'DNI',
                    'documento_numero' => $u?->documento_numero ? substr($u->documento_numero, 0, 4) . '****' : null,
                ],
                'datos_registro' => [
                    'numero_titulo' => $titulacion->numero_titulo,
                    'numero_registro' => $titulacion->numero_registro,
                    'numero_folio' => $titulacion->numero_folio,
                    'numero_libro' => $titulacion->numero_libro,
                    'resolucion_directoral' => $titulacion->resolucion_directoral,
                    'modalidad_titulacion' => $titulacion->modalidad_titulacion,
                    'fecha_sustentacion' => $titulacion->fecha_sustentacion?->format('d/m/Y'),
                    'fecha_emision' => $titulacion->fecha_emision?->format('d/m/Y'),
                    'codigo_minedu' => $titulacion->codigo_minedu ?? 'EN_PROCESO_REGISTRA',
                    'codigo_verificacion' => $titulacion->codigo_verificacion,
                ],
                'mensaje' => 'Título Profesional Técnico verificado conforme a la Ley N° 30512.'
            ]);
        }

        // 2. Buscar en Certificados Modulares
        $certificado = CertificadoModular::withoutGlobalScopes()
            ->with(['institucion', 'estudiante.usuario', 'programa'])
            ->where('codigo_verificacion', $codigo)
            ->first();

        if ($certificado) {
            $u = $certificado->estudiante?->usuario;
            return response()->json([
                'tipo_documento' => 'CERTIFICADO_MODULAR',
                'valido' => $certificado->estado !== 'ANULADO',
                'estado' => $certificado->estado,
                'institucion' => $certificado->institucion?->nombre,
                'codigo_modular_institucion' => $certificado->institucion?->codigo_modular,
                'programa_estudio' => $certificado->programa?->nombre,
                'estudiante' => [
                    'apellidos_nombres' => trim(trim(($u?->apellido_paterno ?? '') . ' ' . ($u?->apellido_materno ?? '')) . ', ' . ($u?->nombres ?? '')),
                    'documento_tipo' => $u?->documento_tipo ?? 'DNI',
                    'documento_numero' => $u?->documento_numero ? substr($u->documento_numero, 0, 4) . '****' : null,
                ],
                'datos_modulo' => [
                    'numero_modulo' => $certificado->numero_modulo,
                    'nombre_modulo' => $certificado->nombre_modulo,
                    'resolucion_directoral' => $certificado->resolucion_directoral,
                    'creditos' => (float)$certificado->creditos,
                    'horas_teoria' => $certificado->horas_teoria,
                    'horas_practica' => $certificado->horas_practica,
                    'horas_efsrt' => $certificado->horas_efsrt,
                    'fecha_emision' => $certificado->fecha_emision?->format('d/m/Y'),
                    'codigo_verificacion' => $certificado->codigo_verificacion,
                ],
                'mensaje' => 'Certificado Modular oficial verificado según RVM N° 277-2019-MINEDU.'
            ]);
        }

        return response()->json([
            'valido' => false,
            'mensaje' => 'El código de verificación no corresponde a ningún documento oficial registrado en la institución.'
        ], 404);
    }
}
