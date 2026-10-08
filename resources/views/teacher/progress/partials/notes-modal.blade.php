<!-- 1. Add Note & Notes List Modal Backdrop -->
<div class="progress-modal-backdrop" id="note-modal" hidden role="dialog" aria-modal="true" aria-labelledby="note-modal-title">
    <div class="progress-modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="note-modal-title">
                <x-icon name="clipboard" size="18" class="text-teal inline-icon" />
                Teacher Notes &middot; {{ $student->full_name }}
            </h3>
            <button type="button" class="modal-close-btn" data-close-modal aria-label="Close dialog">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Privacy notice -->
            <div class="modal-privacy-callout">
                <x-icon name="check" size="14" class="text-teal flex-shrink-0" />
                <span>Teacher notes are strictly confidential and private to you and school administration. They are never exposed to students or parents.</span>
            </div>

            <!-- New Note Form -->
            <form method="POST" action="{{ route('teacher.progress.notes.store', $student->id) }}" class="note-create-form">
                @csrf
                <input type="hidden" name="session_id" value="{{ $sessionId }}">

                <div class="form-group mb-3">
                    <label for="note-tag-select" class="form-label">Category Tag <span class="text-danger">*</span></label>
                    <select name="tag" id="note-tag-select" class="form-select" required>
                        @foreach(\App\Models\StudentNote::TAGS as $tag)
                            <option value="{{ $tag }}">{{ $tag }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label for="note-text-input" class="form-label">Observation / Follow-up Note <span class="text-danger">*</span></label>
                    <textarea name="note" id="note-text-input" rows="3" class="form-textarea"
                              placeholder="Document academic progress, strengths, behavioral concerns, or parent communication details..." required></textarea>
                </div>

                <div class="form-group mb-3">
                    <label for="note-follow-up-date" class="form-label">Follow-up Date (Optional)</label>
                    <input type="date" name="follow_up_date" id="note-follow-up-date" class="form-input" min="{{ today()->toDateString() }}">
                    <span class="text-xs text-mute block mt-1">If set, an alert will remind you on your teacher dashboard when this date passes.</span>
                </div>

                <div class="text-right">
                    <button type="submit" class="button-primary btn-save-note">Save Note</button>
                </div>
            </form>

            <hr class="modal-divider my-4">

            <!-- Existing Notes List -->
            <div class="existing-notes-section">
                <h4 class="text-sm font-bold text-ink mb-3">Existing Notes ({{ $notes->count() }})</h4>

                @if($notes->isEmpty())
                    <p class="text-xs text-mute text-center py-4">No notes recorded yet for this student in the current session.</p>
                @else
                    <div class="notes-feed-list">
                        @foreach($notes as $n)
                            <div class="note-feed-item @if($n->isOverdue()) note-overdue @endif">
                                <div class="note-feed-header">
                                    <div class="flex items-center gap-2">
                                        <span class="note-tag-pill tag-{{ strtolower(str_replace(' ', '-', $n->tag)) }}">
                                            {{ $n->tag }}
                                        </span>
                                        @if($n->isOverdue())
                                            <span class="badge status-danger" title="Follow-up date passed on {{ $n->follow_up_date->format('d M Y') }}">
                                                Overdue Follow-up
                                            </span>
                                        @elseif($n->follow_up_date)
                                            <span class="badge status-neutral" title="Scheduled follow up">
                                                Follow-up: {{ $n->follow_up_date->format('d M') }}
                                            </span>
                                        @endif
                                        @if($n->is_done)
                                            <span class="badge status-ok">Completed</span>
                                        @endif
                                    </div>

                                    <div class="note-meta-right text-xs text-mute">
                                        <span>{{ $n->created_at->format('d M Y, h:i A') }}</span>
                                        @if($n->teacher)
                                            &middot; <span>{{ $n->teacher->name }}</span>
                                        @endif
                                    </div>
                                </div>

                                <p class="note-feed-body">{{ $n->note }}</p>

                                @can('update', $n)
                                    <div class="note-feed-actions">
                                        <form method="POST" action="{{ route('teacher.progress.notes.update', $n->id) }}" class="inline">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="note" value="{{ $n->note }}">
                                            <input type="hidden" name="tag" value="{{ $n->tag }}">
                                            <input type="hidden" name="follow_up_date" value="{{ $n->follow_up_date?->toDateString() }}">
                                            <input type="hidden" name="is_done" value="{{ $n->is_done ? '0' : '1' }}">
                                            <button type="submit" class="btn-text-action">
                                                {{ $n->is_done ? 'Mark Unresolved' : 'Mark as Done' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('teacher.progress.notes.destroy', $n->id) }}" class="inline" onsubmit="return confirm('Delete this note permanently?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-text-action text-danger">Delete</button>
                                        </form>
                                    </div>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- 2. Export PDF Comment Modal Backdrop -->
<div class="progress-modal-backdrop" id="pdf-comment-modal" hidden role="dialog" aria-modal="true" aria-labelledby="pdf-modal-title">
    <div class="progress-modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="pdf-modal-title">
                <x-icon name="clipboard" size="18" class="text-teal inline-icon" />
                Export Student Progress Report &middot; {{ $student->full_name }}
            </h3>
            <button type="button" class="modal-close-btn" data-close-modal aria-label="Close dialog">&times;</button>
        </div>

        <form method="GET" action="{{ route('teacher.progress.export.pdf', $student->id) }}" target="_blank">
            <div class="modal-body">
                <p class="text-sm text-mute mb-3">
                    A formal one-page PDF will be generated including attendance metrics, assessment progression chart, subject breakdown, and grade summaries.
                </p>

                <div class="form-group mb-3">
                    <label for="pdf-teacher-comment" class="form-label">Optional Teacher Remarks / Recommendations</label>
                    <textarea name="comment" id="pdf-teacher-comment" rows="3" class="form-textarea"
                              placeholder="e.g. Excellent progress this term. Continued practice in English reading is recommended."></textarea>
                    <span class="text-xs text-mute block mt-1">These remarks will appear in the dedicated Teacher Remarks section of the PDF.</span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="button-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="button-primary">Download PDF Report</button>
            </div>
        </form>
    </div>
</div>
