<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Matricula;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CajaController extends Controller
{
    /**
     * Listar pagos registrados con filtros, orden y estadísticas.
     */
    public function indexPagos(Request $request)
    {
        $user = auth()->user();
        $query = Pago::with(['estudiante.usuario', 'registradoPor'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id');

        // Filtro de búsqueda general
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero_comprobante', 'ilike', "%{$search}%")
                  ->orWhere('concepto', 'ilike', "%{$search}%")
                  ->orWhere('numero_operacion', 'ilike', "%{$search}%")
                  ->orWhereHas('estudiante', function ($qe) use ($search) {
                      $qe->where('codigo_estudiante', 'ilike', "%{$search}%")
                         ->orWhereHas('usuario', function ($qu) use ($search) {
                             $qu->where('nombres', 'ilike', "%{$search}%")
                                ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                                ->orWhere('apellido_materno', 'ilike', "%{$search}%")
                                ->orWhere('documento_numero', 'ilike', "%{$search}%");
                         });
                  });
            });
        }

        // Filtro por método de pago
        if ($request->filled('metodo_pago')) {
            $query->where('metodo_pago', $request->input('metodo_pago'));
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        // Filtro por rango de fechas
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_pago', '>=', $request->input('fecha_inicio'));
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_pago', '<=', $request->input('fecha_fin'));
        }

        $pagos = $query->paginate($request->get('per_page', 15));

        // Estadísticas / KPIs de caja
        $hoy = Carbon::today();
        $totalHoy = (float) Pago::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->where('estado', 'PAGADO')
            ->whereDate('fecha_pago', $hoy)
            ->sum('monto');

        $totalGeneral = (float) Pago::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->where('estado', 'PAGADO')
            ->sum('monto');

        $cantidadPagos = (int) Pago::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->where('estado', 'PAGADO')
            ->count();

        $cantidadAnulados = (int) Pago::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->where('estado', 'ANULADO')
            ->count();

        $items = $pagos->getCollection()->map(function ($pago) {
            $est = $pago->estudiante;
            $usr = $est?->usuario;
            return [
                'id' => $pago->id,
                'numero_comprobante' => $pago->numero_comprobante,
                'tipo_comprobante' => $pago->tipo_comprobante,
                'concepto' => $pago->concepto,
                'monto' => (float) $pago->monto,
                'metodo_pago' => $pago->metodo_pago,
                'numero_operacion' => $pago->numero_operacion,
                'fecha_pago' => $pago->fecha_pago?->toIso8601String(),
                'estado' => $pago->estado,
                'motivo_anulacion' => $pago->motivo_anulacion,
                'observaciones' => $pago->observaciones,
                'estudiante' => $est ? [
                    'id' => $est->id,
                    'codigo_estudiante' => $est->codigo_estudiante,
                    'nombre_completo' => trim(($usr->nombres ?? '') . ' ' . ($usr->apellido_paterno ?? '') . ' ' . ($usr->apellido_materno ?? '')),
                    'numero_documento' => $usr->numero_documento ?? null,
                ] : null,
                'registrado_por' => $pago->registradoPor ? [
                    'id' => $pago->registradoPor->id,
                    'nombres' => $pago->registradoPor->nombres,
                    'apellido_paterno' => $pago->registradoPor->apellido_paterno,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $items,
            'kpis' => [
                'total_hoy' => $totalHoy,
                'total_general' => $totalGeneral,
                'cantidad_pagos' => $cantidadPagos,
                'cantidad_anulados' => $cantidadAnulados,
                'promedio_ticket' => $cantidadPagos > 0 ? round($totalGeneral / $cantidadPagos, 2) : 0,
            ],
            'meta' => [
                'current_page' => $pagos->currentPage(),
                'last_page' => $pagos->lastPage(),
                'per_page' => $pagos->perPage(),
                'total' => $pagos->total(),
            ],
            'message' => 'Listado de pagos obtenido correctamente.',
        ]);
    }

    /**
     * Registrar un nuevo cobro en la base de datos.
     */
    public function storePago(Request $request)
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
                'nullable',
                Rule::exists('estudiantes', 'id')->where('institucion_id', $institucionId),
            ],
            'matricula_id' => [
                'nullable',
                Rule::exists('matriculas', 'id')->where('institucion_id', $institucionId),
            ],
            'tramite_id' => [
                'nullable',
                Rule::exists('tramites_estudiante', 'id')->where('institucion_id', $institucionId),
            ],
            'tipo_comprobante' => 'nullable|string|in:RECIBO,BOLETA,FACTURA,TICKET',
            'concepto' => 'required|string|max:180',
            'monto' => 'required|numeric|min:0.01|max:999999.99',
            'metodo_pago' => 'required|string|in:EFECTIVO,TRANSFERENCIA,YAPE,PLIN,TARJETA_DEBITO,TARJETA_CREDITO,DEPOSITO_BANCARIO',
            'numero_operacion' => 'nullable|string|max:60',
            'fecha_pago' => 'nullable|date',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($validated, $user, $institucionId) {
            // Generar número correlativo de comprobante institucional: REC-YYYYMMDD-XXXX
            $datePrefix = Carbon::now()->format('Ymd');
            $tipoPrefijo = match ($validated['tipo_comprobante'] ?? 'RECIBO') {
                'BOLETA' => 'BOL',
                'FACTURA' => 'FAC',
                'TICKET' => 'TCK',
                default => 'REC',
            };

            $ultimoPago = Pago::withoutGlobalScopes()
                ->where('institucion_id', $institucionId)
                ->where('numero_comprobante', 'like', "{$tipoPrefijo}-{$datePrefix}-%")
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $nextCorrelativo = 1;
            if ($ultimoPago && preg_match('/-(\d+)$/', $ultimoPago->numero_comprobante, $matches)) {
                $nextCorrelativo = ((int) $matches[1]) + 1;
            }

            $numeroComprobante = sprintf('%s-%s-%04d', $tipoPrefijo, $datePrefix, $nextCorrelativo);

            $pago = Pago::create([
                'institucion_id' => $institucionId,
                'estudiante_id' => $validated['estudiante_id'] ?? null,
                'matricula_id' => $validated['matricula_id'] ?? null,
                'tramite_id' => $validated['tramite_id'] ?? null,
                'numero_comprobante' => $numeroComprobante,
                'tipo_comprobante' => $validated['tipo_comprobante'] ?? 'RECIBO',
                'concepto' => $validated['concepto'],
                'monto' => $validated['monto'],
                'metodo_pago' => $validated['metodo_pago'],
                'numero_operacion' => $validated['numero_operacion'] ?? null,
                'fecha_pago' => !empty($validated['fecha_pago']) ? Carbon::parse($validated['fecha_pago']) : Carbon::now(),
                'estado' => 'PAGADO',
                'observaciones' => $validated['observaciones'] ?? null,
                'registrado_por_usuario_id' => $user->id,
            ]);

            $pago->load(['estudiante.usuario', 'registradoPor']);

            return response()->json([
                'data' => $pago,
                'message' => 'Cobro registrado exitosamente con comprobante ' . $pago->numero_comprobante,
            ], 201);
        });
    }

    /**
     * Consultar detalle de un pago específico.
     */
    public function showPago($id)
    {
        $user = auth()->user();
        $pago = Pago::with(['estudiante.usuario', 'estudiante.programaEstudios', 'matricula.grupoCurso.curso', 'tramite', 'registradoPor'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($id);

        return response()->json([
            'data' => $pago,
            'message' => 'Detalle de pago obtenido.',
        ]);
    }

    /**
     * Anular un comprobante de pago con motivo explícito.
     */
    public function anularPago(Request $request, $id)
    {
        $validated = $request->validate([
            'motivo_anulacion' => 'required|string|min:5|max:500',
        ]);

        $user = auth()->user();
        $pago = Pago::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($id);

        if ($pago->estado === 'ANULADO') {
            return response()->json([
                'message' => 'El pago ya se encuentra anulado.',
            ], 422);
        }

        $pago->update([
            'estado' => 'ANULADO',
            'motivo_anulacion' => $validated['motivo_anulacion'],
        ]);

        return response()->json([
            'data' => $pago,
            'message' => 'El comprobante ' . $pago->numero_comprobante . ' fue anulado correctamente.',
        ]);
    }

    /**
     * Búsqueda reactiva de estudiantes para el formulario de cobranza.
     */
    public function buscarEstudiantes(Request $request)
    {
        $user = auth()->user();
        $q = trim($request->input('q', ''));

        $query = Estudiante::with(['usuario', 'programaEstudios'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id));

        if (!empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo_estudiante', 'ilike', "%{$q}%")
                    ->orWhereHas('usuario', function ($qu) use ($q) {
                        $qu->where('nombres', 'ilike', "%{$q}%")
                           ->orWhere('apellido_paterno', 'ilike', "%{$q}%")
                           ->orWhere('apellido_materno', 'ilike', "%{$q}%")
                           ->orWhere('documento_numero', 'ilike', "%{$q}%");
                    });
            });
        }

        $estudiantes = $query->limit(25)->get()->map(function ($est) {
            $usr = $est->usuario;
            return [
                'id' => $est->id,
                'codigo_estudiante' => $est->codigo_estudiante,
                'nombre_completo' => trim(($usr->nombres ?? '') . ' ' . ($usr->apellido_paterno ?? '') . ' ' . ($usr->apellido_materno ?? '')),
                'numero_documento' => $usr->numero_documento ?? null,
                'programa' => $est->programaEstudios?->nombre ?? null,
            ];
        });

        return response()->json([
            'data' => $estudiantes,
            'message' => 'Estudiantes encontrados.',
        ]);
    }

    /**
     * Consulta operativa de matrículas y estado de cobro (mantiene retrocompatibilidad).
     */
    public function resumen(Request $request)
    {
        $user = auth()->user();

        $query = Matricula::with(['estudiante.usuario', 'grupoCurso.curso', 'grupoCurso.periodo'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->orderByDesc('fecha_matricula');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('estudiante_id')) {
            $query->where('estudiante_id', $request->input('estudiante_id'));
        }

        $matriculas = $query->paginate($request->get('per_page', 15));

        $items = $matriculas->getCollection()->map(function ($matricula) {
            return [
                'id' => $matricula->id,
                'estudiante' => $matricula->estudiante ? [
                    'id' => $matricula->estudiante->id,
                    'nombre_completo' => trim(($matricula->estudiante->usuario->nombres ?? '') . ' ' . ($matricula->estudiante->usuario->apellido_paterno ?? '') . ' ' . ($matricula->estudiante->usuario->apellido_materno ?? '')),
                    'codigo_estudiante' => $matricula->estudiante->codigo_estudiante,
                ] : null,
                'curso' => $matricula->grupoCurso?->curso ? [
                    'id' => $matricula->grupoCurso->curso->id,
                    'nombre' => $matricula->grupoCurso->curso->nombre,
                    'codigo' => $matricula->grupoCurso->curso->codigo,
                ] : null,
                'periodo' => $matricula->grupoCurso?->periodo ? [
                    'id' => $matricula->grupoCurso->periodo->id,
                    'nombre' => $matricula->grupoCurso->periodo->nombre,
                    'codigo' => $matricula->grupoCurso->periodo->codigo,
                ] : null,
                'estado' => $matricula->estado,
                'estado_cobro' => 'REGISTRADO',
                'fecha_matricula' => $matricula->fecha_matricula,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $matriculas->currentPage(),
                'last_page' => $matriculas->lastPage(),
                'per_page' => $matriculas->perPage(),
                'total' => $matriculas->total(),
            ],
            'message' => 'Consulta de caja disponible.',
        ]);
    }

    public function operaciones(Request $request)
    {
        return $this->resumen($request);
    }
}
