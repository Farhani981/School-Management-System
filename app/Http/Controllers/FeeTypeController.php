<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeeTypeRequest;
use App\Models\FeeType;
use App\Services\FeeSetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeTypeController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100']);

        return view('fees.types.index', ['types' => FeeType::when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))->orderBy('name')->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('fees.types.form', ['type' => new FeeType]);
    }

    public function store(FeeTypeRequest $request)
    {
        FeeType::create($request->validated());

        return redirect()->route('fees.setup.types.index')->with('success', 'Fee type added.');
    }

    public function edit(FeeType $type)
    {
        return view('fees.types.form', compact('type'));
    }

    public function update(FeeTypeRequest $request, FeeType $type)
    {
        DB::transaction(fn () => FeeType::whereKey($type->id)->lockForUpdate()->firstOrFail()->update($request->validated()));

        return redirect()->route('fees.setup.types.index')->with('success', 'Fee type updated.');
    }

    public function destroy(FeeType $type, FeeSetupService $service)
    {
        $deleted = DB::transaction(function () use ($type, $service) {
            $type = FeeType::whereKey($type->id)->lockForUpdate()->firstOrFail();
            if ($service->typeInUse($type)) {
                return false;
            }
            $type->delete();

            return true;
        });

        return back()->with($deleted ? 'success' : 'error', $deleted ? 'Fee type archived.' : 'Cannot delete: this fee type is used in a structure, voucher, discount or student override. Deactivate it instead.');
    }
}
