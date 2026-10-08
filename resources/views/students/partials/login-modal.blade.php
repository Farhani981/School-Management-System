@props(['student'])

<div 
    id="login-modal" 
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs transition-opacity duration-200 hidden"
    role="dialog" 
    aria-modal="true" 
    aria-labelledby="login-modal-title"
>
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-start justify-between pb-3 border-b border-slate-100 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-[#e8f7fa] text-[#0A7EA4] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <div>
                    <h3 id="login-modal-title" class="text-sm font-bold text-slate-800">
                        {{ ($student->user_id && $student->user) ? 'Portal Login Credentials' : 'Create Portal Login' }}
                    </h3>
                    <p class="text-[11px] text-slate-500">Student: {{ $student->full_name }}</p>
                </div>
            </div>
            <button type="button" onclick="closeLoginModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        @if($student->user_id && $student->user)
            <!-- Existing Account View -->
            <div class="space-y-3.5 mb-5 text-xs">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Account Status</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Portal Email</span>
                        <span class="font-mono text-slate-800 select-all font-semibold">{{ $student->user->email }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Access Level</span>
                        <span class="text-slate-700 font-medium">Student Portal</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Account Created</span>
                        <span class="text-slate-600">{{ $student->user->created_at ? $student->user->created_at->format('d-m-Y') : 'N/A' }}</span>
                    </div>
                </div>

                <div class="pt-2">
                    <h4 class="text-xs font-bold text-slate-700 mb-2">Reset Password</h4>
                    <form method="POST" action="{{ route('students.portal-login', $student) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="email" value="{{ $student->user->email }}">
                        <div>
                            <input 
                                type="password" 
                                name="password" 
                                minlength="8" 
                                required 
                                placeholder="Enter new password (min 8 chars)" 
                                class="w-full text-xs rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 focus:border-[#0A7EA4]"
                            >
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="submit" class="px-3.5 py-1.5 text-xs font-semibold text-white bg-[#0A7EA4] hover:bg-[#086987] rounded-lg shadow-sm">
                                Save New Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            {{-- Form for setting up initial credentials --}}
            <form method="POST" action="{{ route('students.portal-login', $student) }}" class="space-y-4">
                @csrf
                <p class="text-xs text-slate-600">
                    Create student portal credentials so this student can sign in to view class assignments, attendance records, and notices.
                </p>

                <div>
                    <label for="portal-email" class="block text-xs font-bold text-slate-700 mb-1.5">Portal Email / Username *</label>
                    <input 
                        id="portal-email" 
                        type="email" 
                        name="email" 
                        required 
                        value="{{ old('email', $student->email ?: strtolower(str_replace(' ', '', $student->first_name)) . '.' . $student->admission_no . '@school.edu') }}"
                        class="w-full text-xs rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 focus:border-[#0A7EA4]"
                    >
                </div>

                <div>
                    <label for="portal-password" class="block text-xs font-bold text-slate-700 mb-1.5">Initial Password</label>
                    <input 
                        id="portal-password" 
                        type="text" 
                        name="password" 
                        minlength="8" 
                        value="Student@123" 
                        class="w-full text-xs rounded-lg border border-slate-300 px-3 py-2 font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 focus:border-[#0A7EA4]"
                    >
                    <p class="text-[11px] text-slate-400 mt-1">Default password provided. Student can change it on initial login.</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeLoginModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-[#0A7EA4] hover:bg-[#086987] rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                        <span>Create Student Account</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

<script>
    function openLoginModal() {
        const modal = document.getElementById('login-modal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeLoginModal() {
        const modal = document.getElementById('login-modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
</script>
