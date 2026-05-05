<?php

namespace App\Http\Controllers;

use App\Models\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommandStorageController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $category = $request->get('category', 'all');
        $favorites = $request->get('favorites', false);
        
        $query = Command::query();
        
        if ($search) {
            $query->search($search);
        }
        
        if ($category !== 'all') {
            $query->category($category);
        }
        
        if ($favorites) {
            $query->where('is_favorite', true);
        }
        
        $commands = $query->orderBy('usage_count', 'desc')
                         ->orderBy('name', 'asc')
                         ->paginate(20);
        
        $categories = Command::select('category')
            ->distinct()
            ->pluck('category');
        
        $favoriteCommands = Command::where('is_favorite', true)->count();
        
        return view('command-storage.index', compact('commands', 'categories', 'favoriteCommands', 'search', 'category', 'favorites'));
    }
    
    public function show($id)
    {
        $command = Command::findOrFail($id);
        $command->incrementUsage();
        
        return response()->json([
            'success' => true,
            'command' => $command
        ]);
    }
    
    public function toggleFavorite($id)
    {
        $command = Command::findOrFail($id);
        $command->is_favorite = !$command->is_favorite;
        $command->save();
        
        return response()->json([
            'success' => true,
            'is_favorite' => $command->is_favorite
        ]);
    }
    
    public function incrementUsage($id)
    {
        $command = Command::findOrFail($id);
        $command->incrementUsage();
        
        return response()->json(['success' => true]);
    }
    
    public function getCategories()
    {
        $categories = Command::select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->get();
        
        return response()->json($categories);
    }
}