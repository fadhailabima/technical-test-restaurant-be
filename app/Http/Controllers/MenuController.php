<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class MenuController extends Controller
{
    #[OA\Get(
        path: "/api/menus",
        tags: ["Menus"],
        summary: "Get all menus",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "category", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["makanan", "minuman", "snack", "dessert"])),
            new OA\Parameter(name: "is_available", in: "query", required: false, schema: new OA\Schema(type: "boolean")),
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Menu list retrieved successfully")
        ]
    )]
    public function index(Request $request)
    {
        $query = Menu::query();

        // Filter by category
        if ($request->has('category')) {
            $query->category($request->category);
        }

        // Filter by availability
        if ($request->has('is_available')) {
            $query->where('is_available', $request->boolean('is_available'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $menus = $query->orderBy('category')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }

    #[OA\Post(
        path: "/api/menus",
        tags: ["Menus"],
        summary: "Create new menu",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["name", "price", "category"],
                    properties: [
                        new OA\Property(property: "name", type: "string", example: "Nasi Goreng"),
                        new OA\Property(property: "description", type: "string", example: "Nasi goreng spesial"),
                        new OA\Property(property: "price", type: "number", example: 25000),
                        new OA\Property(property: "category", type: "string", enum: ["makanan", "minuman", "snack", "dessert"]),
                        new OA\Property(property: "image", type: "string", format: "binary"),
                        new OA\Property(property: "is_available", type: "boolean", example: true)
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Menu created successfully")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|in:makanan,minuman,snack,dessert',
            'image' => 'nullable|image|max:2048',
            'is_available' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('menus', 'public');
        }

        $menu = Menu::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Menu created successfully',
            'data' => $menu,
        ], 201);
    }

    #[OA\Get(
        path: "/api/menus/{id}",
        tags: ["Menus"],
        summary: "Get menu detail",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Menu detail retrieved successfully")
        ]
    )]
    public function show(Menu $menu)
    {
        return response()->json([
            'success' => true,
            'data' => $menu,
        ]);
    }

    #[OA\Put(
        path: "/api/menus/{id}",
        tags: ["Menus"],
        summary: "Update menu",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "description", type: "string"),
                        new OA\Property(property: "price", type: "number"),
                        new OA\Property(property: "category", type: "string", enum: ["makanan", "minuman", "snack", "dessert"]),
                        new OA\Property(property: "image", type: "string", format: "binary"),
                        new OA\Property(property: "is_available", type: "boolean")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Menu updated successfully")
        ]
    )]
    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'category' => 'sometimes|required|in:makanan,minuman,snack,dessert',
            'image' => 'nullable|image|max:2048',
            'is_available' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($menu->image) {
                Storage::disk('public')->delete($menu->image);
            }
            $validated['image'] = $request->file('image')->store('menus', 'public');
        }

        $menu->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Menu updated successfully',
            'data' => $menu->fresh(),
        ]);
    }

    #[OA\Delete(
        path: "/api/menus/{id}",
        tags: ["Menus"],
        summary: "Delete menu",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Menu deleted successfully")
        ]
    )]
    public function destroy(Menu $menu)
    {
        // Delete image if exists
        if ($menu->image) {
            Storage::disk('public')->delete($menu->image);
        }

        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu deleted successfully',
        ]);
    }
}
