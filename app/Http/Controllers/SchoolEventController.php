<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolEventRequest;
use App\Models\SchoolEvent;
use Illuminate\Http\Request;

class SchoolEventController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100']);

        return view('events.index', ['events' => SchoolEvent::when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%'.$request->search.'%'))->orderByDesc('date')->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('events.form', ['event' => new SchoolEvent]);
    }

    public function store(SchoolEventRequest $request)
    {
        SchoolEvent::create($request->validated());

        return redirect()->route('events.index')->with('success', 'Event added.');
    }

    public function edit(SchoolEvent $event)
    {
        return view('events.form', compact('event'));
    }

    public function update(SchoolEventRequest $request, SchoolEvent $event)
    {
        $event->update($request->validated());

        return redirect()->route('events.index')->with('success', 'Event updated.');
    }

    public function destroy(SchoolEvent $event)
    {
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted.');
    }
}
