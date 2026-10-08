@props(['student'])

<div 
    id="status-modal" 
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs transition-opacity duration-200 hidden"
    role="dialog" 
    aria-modal="true" 
    aria-labelledby="status-modal-title"
>
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-start justify-between pb-3 border-b border-slate-100 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h3 id="status-modal-title" class="text-sm font-bold text-slate-800">Change Student Status</h3>
                    <p class="text-[11px] text-slate-500">Student: {{ $student->full_name }} ({{ $student->admission_no }})</p>
                </div>
            </div>
            <button type="button" onclick="closeStatusModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('students.status', $student) }}" class="space-y-4">
            @csrf
            <div>
                <label for="new-status" class="block text-xs font-bold text-slate-700 mb-1.5">New Status *</label>
                <select id="new-status" name="status" required class="w-full text-xs rounded-lg border border-slate-300 px-3 py-2 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 focus:border-[#0A7EA4]">
                    <option value="Active" @selected($student->status === 'Active')>Active (Enrolled & Attending)</option>
                    <option value="Left" @selected(in_array($student->status, ['Left', 'Left School']))>Left (Left School / Transferred)</option>
                    <option value="Passed out" @selected(in_array($student->status, ['Passed out', 'Graduated']))>Passed out (Completed Education)</option>
                    <option value="Inactive" @selected($student->status === 'Inactive')>Inactive (Temporarily Suspended)</option>
                </select>
            </div>

            <div>
                <label for="status-reason" class="block text-xs font-bold text-slate-700 mb-1.5">Reason for Change *</label>
                <textarea 
                    id="status-reason" 
                    name="reason" 
                    rows="3" 
                    required 
                    maxlength="1000" 
                    placeholder="Enter the official reason for changing this student's status..." 
                    class="w-full text-xs rounded-lg border border-slate-300 p-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 focus:border-[#0A7EA4]"
                ></textarea>
                <p class="text-[11px] text-slate-400 mt-1">This change and reason will be permanently logged in the student's audit history.</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeStatusModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                    <span>Confirm Status Update</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openStatusModal() {
        const modal = document.getElementById('status-modal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeStatusModal() {
        const modal = document.getElementById('status-modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
</script>
