<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TableController extends Controller
{
    #[OA\Get(
        path: "/api/tables",
        tags: ["Tables"],
        summary: "Get all tables",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["available", "occupied", "reserved"]))
        ],
        responses: [
            new OA\Response(response: 200, description: "Table list retrieved successfully")
        ]
    )]
    public function index(Request $request)
    {
        $query = Table::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tables = $query->orderBy('table_number')->get();

        return response()->json([
            'success' => true,
            'data' => $tables,
        ]);
    }

    #[OA\Post(
        path: "/api/tables",
        tags: ["Tables"],
        summary: "Create new table",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["table_number", "capacity"],
                properties: [
                    new OA\Property(property: "table_number", type: "string", example: "T11"),
                    new OA\Property(property: "capacity", type: "integer", example: 4)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Table created successfully")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'table_number' => 'required|string|max:10|unique:tables',
            'capacity' => 'required|integer|min:1|max:50',
        ]);

        $table = Table::create([
            'table_number' => $validated['table_number'],
            'capacity' => $validated['capacity'],
            'status' => 'available',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meja berhasil ditambahkan',
            'data' => $table,
        ], 201);
    }

    #[OA\Get(
        path: "/api/tables/{id}",
        tags: ["Tables"],
        summary: "Get table detail",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Table detail retrieved successfully")
        ]
    )]
    public function show(Table $table)
    {
        return response()->json([
            'success' => true,
            'data' => $table->load('currentOrder'),
        ]);
    }

    #[OA\Put(
        path: "/api/tables/{id}",
        tags: ["Tables"],
        summary: "Update table",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "table_number", type: "string"),
                    new OA\Property(property: "capacity", type: "integer"),
                    new OA\Property(property: "status", type: "string", enum: ["available", "occupied", "reserved"])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Table updated successfully")
        ]
    )]
    public function update(Request $request, Table $table)
    {
        $validated = $request->validate([
            'table_number' => 'sometimes|string|max:10|unique:tables,table_number,' . $table->id,
            'capacity' => 'sometimes|integer|min:1|max:50',
            'status' => 'sometimes|in:available,occupied,reserved',
        ]);

        $table->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Meja berhasil diupdate',
            'data' => $table,
        ]);
    }

    #[OA\Delete(
        path: "/api/tables/{id}",
        tags: ["Tables"],
        summary: "Delete table",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Table deleted successfully")
        ]
    )]
    public function destroy(Table $table)
    {
        if ($table->status === 'occupied') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa menghapus meja yang sedang digunakan',
            ], 400);
        }

        $table->delete();

        return response()->json([
            'success' => true,
            'message' => 'Meja berhasil dihapus',
        ]);
    }
}
