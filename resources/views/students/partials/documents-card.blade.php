@props(['student', 'documents' => []])

<x-student.detail-card title="Documents">
    <x-slot:icon>
        <svg class="w-4 h-4 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
    </x-slot:icon>

    @can('update', $student)
        <x-slot:action>
            <button 
                type="button" 
                onclick="document.getElementById('doc-upload-collapse').classList.toggle('hidden')"
                class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-[#0A7EA4] bg-[#e8f7fa] hover:bg-[#d5eff5] rounded-md transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Upload</span>
            </button>
        </x-slot:action>
    @endcan

    <!-- Quick upload form (collapsible) -->
    @can('update', $student)
        <div id="doc-upload-collapse" class="hidden mb-4 p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
            <form method="POST" action="{{ route('students.documents.store', $student) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label for="new-student-docs" class="block text-xs font-semibold text-slate-700 mb-1">Select Document(s)</label>
                    <input 
                        id="new-student-docs" 
                        type="file" 
                        name="documents[]" 
                        multiple 
                        accept=".pdf,.jpg,.jpeg,.png" 
                        required
                        class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-[#e8f7fa] file:text-[#0A7EA4] hover:file:bg-[#d5eff5]"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">PDF, JPG, PNG up to 5 MB each.</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('doc-upload-collapse').classList.add('hidden')" class="px-3 py-1 text-xs text-slate-600 hover:bg-slate-200 rounded">Cancel</button>
                    <button type="submit" class="px-3 py-1 text-xs font-semibold text-white bg-[#0A7EA4] hover:bg-[#086987] rounded shadow-xs">Upload Document</button>
                </div>
            </form>
        </div>
    @endcan

    @if($documents->isNotEmpty())
        <div class="space-y-2.5">
            @foreach($documents as $doc)
                @php
                    $ext = strtolower(pathinfo($doc->name, PATHINFO_EXTENSION));
                    $isPdf = $ext === 'pdf';
                    $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                @endphp
                <div class="p-3 rounded-lg border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 flex items-center justify-between gap-3 transition-colors shadow-2xs">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg {{ $isPdf ? 'bg-rose-50 text-rose-600' : ($isImg ? 'bg-sky-50 text-sky-600' : 'bg-slate-100 text-slate-600') }} flex items-center justify-center shrink-0">
                            @if($isPdf)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            @elseif($isImg)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            @endif
                        </div>
                        <div class="truncate">
                            <h5 class="text-xs font-semibold text-slate-800 truncate" title="{{ $doc->name }}">{{ $doc->name }}</h5>
                            <span class="text-[10px] text-slate-400 uppercase font-mono">{{ $ext ?: 'FILE' }} &bull; {{ $doc->created_at ? $doc->created_at->format('d-m-Y') : 'Uploaded' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <!-- Download Button (EduDash style teal download button) -->
                        <a 
                            href="{{ route('students.documents.download', [$student, $doc]) }}" 
                            class="p-1.5 rounded-md text-[#0A7EA4] hover:bg-[#e8f7fa] transition-colors"
                            title="Download {{ $doc->name }}"
                            aria-label="Download {{ $doc->name }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        </a>

                        @can('update', $student)
                            <!-- Delete Button with confirmation -->
                            <form 
                                method="POST" 
                                action="{{ route('students.documents.destroy', [$student, $doc]) }}" 
                                onsubmit="return confirm('Are you sure you want to delete document &quot;{{ addslashes($doc->name) }}&quot;?');"
                                class="inline"
                            >
                                @csrf
                                @method('DELETE')
                                <button 
                                    type="submit" 
                                    class="p-1.5 rounded-md text-rose-500 hover:bg-rose-50 transition-colors"
                                    title="Delete document"
                                    aria-label="Delete {{ $doc->name }}"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-6">
            <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <p class="text-xs text-slate-400 italic">No documents uploaded yet</p>
        </div>
    @endif
</x-student.detail-card>
