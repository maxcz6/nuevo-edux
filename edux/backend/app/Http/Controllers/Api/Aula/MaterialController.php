<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    use AuthorizesAulaAccess;

    public function index(Request $request, $unidad)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, false);
        $user = auth()->user();

        $query = Material::where('unidad_id', $unidadModel->id);

        if ($request->has('sesion_id')) {
            $query->where('sesion_id', $request->sesion_id);
        }

        // Estudiantes solo ven materiales activos y cuya fecha visible_desde ya se haya cumplido
        if ($user->hasRole('ESTUDIANTE')) {
            $query->where('estado', 'ACTIVO')
                  ->where(function ($q) {
                      $q->whereNull('visible_desde')
                        ->orWhere('visible_desde', '<=', now());
                  });
        }

        $materiales = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $materiales->items(),
            'meta' => [
                'current_page' => $materiales->currentPage(),
                'last_page'    => $materiales->lastPage(),
                'per_page'     => $materiales->perPage(),
                'total'        => $materiales->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request, $unidad)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, true);
        $user = auth()->user();

        $validated = $request->validate([
            'titulo'         => 'required|string|max:150',
            'descripcion'    => 'nullable|string',
            'tipo'           => 'nullable|in:PDF,DOCX,PPTX,XLSX,IMAGEN,VIDEO,ENLACE,ZIP,DOCUMENTO',
            'enlace_externo' => 'required_if:tipo,ENLACE|nullable|url|max:255',
            'archivo'        => 'required_unless:tipo,ENLACE|nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,png,mp4,zip|max:20480',
            'visible_desde'  => 'nullable|date',
            'sesion_id'      => [
                'nullable', 'integer',
                function ($attr, $val, $fail) use ($unidadModel) {
                    if (!$val) return;
                    $existe = \App\Models\Sesion::where('id', $val)
                        ->where('unidad_id', $unidadModel->id)
                        ->exists();
                    if (!$existe) {
                        $fail('La sesión no pertenece a esta unidad didáctica.');
                    }
                },
            ],
        ]);

        $tipo = $validated['tipo'] ?? null;
        $path = null;

        if ($tipo === 'ENLACE') {
            $tipo = 'ENLACE';
            $path = null;
        } elseif ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $ext = strtolower($file->getClientOriginalExtension());
            $tipoMap = [
                'pdf'  => 'PDF',
                'doc'  => 'DOCX',
                'docx' => 'DOCX',
                'ppt'  => 'PPTX',
                'pptx' => 'PPTX',
                'xls'  => 'XLSX',
                'xlsx' => 'XLSX',
                'jpg'  => 'IMAGEN',
                'jpeg' => 'IMAGEN',
                'png'  => 'IMAGEN',
                'mp4'  => 'VIDEO',
                'zip'  => 'ZIP'
            ];
            $tipo = $tipo ?? ($tipoMap[$ext] ?? 'DOCUMENTO');
            $path = Storage::disk('local')->putFile('materiales', $file);
        } else {
            return response()->json([
                'message' => 'Debe adjuntar un archivo o especificar un enlace externo válido.',
                'errors'  => ['archivo' => ['Debe adjuntar un archivo o especificar un enlace externo válido.']]
            ], 422);
        }

        $docenteId = $user->docente?->id ?? $unidadModel->grupoCurso->docente_id;

        $material = Material::create([
            'unidad_id'      => $unidadModel->id,
            'sesion_id'      => $validated['sesion_id'] ?? null,
            'docente_id'     => $docenteId,
            'titulo'         => $validated['titulo'],
            'descripcion'    => $validated['descripcion'] ?? null,
            'tipo'           => $tipo,
            'archivo_url'    => $path,
            'enlace_externo' => ($tipo === 'ENLACE') ? ($validated['enlace_externo'] ?? null) : null,
            'visible_desde'  => $validated['visible_desde'] ?? null,
            'estado'         => 'ACTIVO'
        ]);

        return response()->json(['data' => $material, 'message' => 'Material subido exitosamente.'], 201);
    }

    public function show($unidad, $id)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, false);
        $user = auth()->user();

        $query = Material::where('unidad_id', $unidadModel->id);

        if ($user->hasRole('ESTUDIANTE')) {
            $query->where('estado', 'ACTIVO')
                  ->where(function ($q) {
                      $q->whereNull('visible_desde')
                        ->orWhere('visible_desde', '<=', now());
                  });
        }

        $material = $query->findOrFail($id);

        return response()->json(['data' => $material, 'message' => 'OK']);
    }

    public function update(Request $request, $unidad, $id)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, true);
        $material = Material::where('unidad_id', $unidadModel->id)->findOrFail($id);

        $validated = $request->validate([
            'titulo'         => 'sometimes|string|max:150',
            'descripcion'    => 'nullable|string',
            'tipo'           => 'sometimes|in:PDF,DOCX,PPTX,XLSX,IMAGEN,VIDEO,ENLACE,ZIP,DOCUMENTO',
            'enlace_externo' => 'nullable|url|max:255',
            'archivo'        => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,png,mp4,zip|max:20480',
            'visible_desde'  => 'nullable|date',
            'estado'         => 'sometimes|in:ACTIVO,INACTIVO',
        ]);

        $targetTipo = $request->input('tipo', $material->tipo);

        if ($targetTipo === 'ENLACE') {
            $nuevoEnlace = $request->input('enlace_externo', $material->enlace_externo);
            if (empty($nuevoEnlace)) {
                return response()->json([
                    'message' => 'El enlace externo es obligatorio para materiales de tipo ENLACE.',
                    'errors'  => ['enlace_externo' => ['El enlace externo es obligatorio para materiales de tipo ENLACE.']]
                ], 422);
            }
            if ($material->archivo_url && Storage::disk('local')->exists($material->archivo_url)) {
                Storage::disk('local')->delete($material->archivo_url);
            }
            $material->archivo_url = null;
            $material->enlace_externo = $nuevoEnlace;
        } else {
            if ($request->hasFile('archivo')) {
                if ($material->archivo_url && Storage::disk('local')->exists($material->archivo_url)) {
                    Storage::disk('local')->delete($material->archivo_url);
                }
                $file = $request->file('archivo');
                $ext = strtolower($file->getClientOriginalExtension());
                $tipoMap = [
                    'pdf'  => 'PDF',
                    'doc'  => 'DOCX',
                    'docx' => 'DOCX',
                    'ppt'  => 'PPTX',
                    'pptx' => 'PPTX',
                    'xls'  => 'XLSX',
                    'xlsx' => 'XLSX',
                    'jpg'  => 'IMAGEN',
                    'jpeg' => 'IMAGEN',
                    'png'  => 'IMAGEN',
                    'mp4'  => 'VIDEO',
                    'zip'  => 'ZIP'
                ];
                if (!$request->has('tipo')) {
                    $targetTipo = $tipoMap[$ext] ?? 'DOCUMENTO';
                }
                $material->archivo_url = Storage::disk('local')->putFile('materiales', $file);
            } elseif ($material->tipo === 'ENLACE' && empty($material->archivo_url)) {
                return response()->json([
                    'message' => 'Debe adjuntar un archivo para materiales de tipo documento.',
                    'errors'  => ['archivo' => ['Debe adjuntar un archivo para materiales de tipo documento.']]
                ], 422);
            }
            $material->enlace_externo = null;
        }

        if ($request->has('titulo')) {
            $material->titulo = $validated['titulo'];
        }
        if (array_key_exists('descripcion', $validated)) {
            $material->descripcion = $validated['descripcion'];
        }
        $material->tipo = $targetTipo;
        if ($request->has('visible_desde')) {
            $material->visible_desde = $validated['visible_desde'];
        }
        if ($request->has('estado')) {
            $material->estado = $validated['estado'];
        }

        $material->save();

        return response()->json(['data' => $material, 'message' => 'Material actualizado exitosamente.']);
    }

    public function destroy($unidad, $id)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, true);
        $material = Material::where('unidad_id', $unidadModel->id)->findOrFail($id);

        if ($material->archivo_url && Storage::disk('local')->exists($material->archivo_url)) {
            Storage::disk('local')->delete($material->archivo_url);
        }

        $material->delete();
        return response()->json(null, 204);
    }

    public function descargar($id)
    {
        $material = Material::with('unidad.grupoCurso')->findOrFail($id);
        $this->verifyGroupAccess($material->unidad->grupo_curso_id, false);
        $user = auth()->user();

        if ($user->hasRole('ESTUDIANTE')) {
            if ($material->estado !== 'ACTIVO' || ($material->visible_desde && $material->visible_desde->isFuture())) {
                abort(response()->json(['message' => 'El material no está disponible para descarga.', 'error' => 'forbidden'], 403));
            }
        }

        if (!$material->archivo_url || !Storage::disk('local')->exists($material->archivo_url)) {
            abort(response()->json(['message' => 'Archivo no encontrado.', 'error' => 'not_found'], 404));
        }

        return Storage::disk('local')->download($material->archivo_url, $material->titulo);
    }
}
