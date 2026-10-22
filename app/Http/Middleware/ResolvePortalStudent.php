<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolvePortalStudent
{
    /**
     * Handle an incoming request for portal routes.
     * Enforces security rules:
     * 1. Never accept student_id from URL or request body.
     * 2. Student role: student_id always comes from authenticated user.
     * 3. Parent role: selected student_id must be verified against parent_student.
     * 4. Parents have read-only access (mutating portal routes blocked).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('portal.login')->with('status', 'Your session expired. Please sign in again.');
        }

        // Security check: Never accept student_id parameter from URL or request body for portal routes
        if ($request->has('student_id') && ! $request->routeIs('portal.switch-child')) {
            $request->query->remove('student_id');
            $request->request->remove('student_id');
        }

        // Determine user portal role
        $isParent = $user->hasRole('Parent');
        $isStudent = $user->hasRole('Student');

        if (! $isStudent && ! $isParent) {
            // Admins/Principals might preview, otherwise block
            if (! $user->hasAnyRole(['Admin', 'Principal'])) {
                abort(403, 'You do not have permission to access the student portal.');
            }
        }

        // If parent role, enforce read-only access to academic/student endpoints
        // Exception: Parents CAN submit leave requests (and cancel pending ones) for their child
        if ($isParent && ! in_array($request->method(), ['GET', 'HEAD'])) {
            $exemptRoutes = [
                'portal.switch-child',
                'portal.password.update',
                'portal.logout',
                'student.leave.store',
                'student.leave.cancel',
                'portal.leave.store',
                'portal.leave.cancel',
                'portal.api.leave.store',
                'portal.api.leave.cancel',
            ];
            if (! in_array($request->route()?->getName(), $exemptRoutes)) {
                abort(403, 'Parents have read-only access to portal resources.');
            }
        }

        $student = null;
        $parentChildren = collect();
        $role = $isParent ? 'parent' : 'student';

        if ($isStudent) {
            $student = Student::with(['schoolClass', 'section', 'session'])
                ->where('user_id', $user->id)
                ->first();

            // Fallback: match by email or pending user_id
            if (! $student && $user->email) {
                $student = Student::with(['schoolClass', 'section', 'session'])
                    ->where('email', $user->email)
                    ->first();
                if ($student && ! $student->user_id) {
                    $student->user_id = $user->id;
                    $student->save();
                }
            }

            if (! $student && ! $request->routeIs('portal.unlinked', 'portal.logout', 'portal.password.*')) {
                return redirect()->route('portal.unlinked');
            }
        } elseif ($isParent) {
            // Fetch children linked in parent_student pivot
            $parentChildren = $user->children()
                ->with(['schoolClass', 'section', 'session'])
                ->get();

            if ($parentChildren->isEmpty() && ! $request->routeIs('portal.unlinked', 'portal.logout', 'portal.password.*')) {
                return redirect()->route('portal.unlinked');
            }

            // Verify selected child against linked children
            $activeStudentId = session('active_student_id');
            if (! $activeStudentId || ! $parentChildren->contains('id', $activeStudentId)) {
                $first = $parentChildren->first();
                $activeStudentId = $first?->id;
                session(['active_student_id' => $activeStudentId]);
            }

            $student = $parentChildren->firstWhere('id', $activeStudentId);
        } else {
            // Admin previewing portal: pick first active student
            $student = Student::with(['schoolClass', 'section', 'session'])->first();
        }

        // Bind verified attributes to request
        $request->attributes->set('portal_student', $student);
        $request->attributes->set('portal_student_id', $student?->id);
        $request->attributes->set('portal_role', $role);
        $request->attributes->set('parent_children', $parentChildren);

        // Share with Blade views
        view()->share('currentStudent', $student);
        view()->share('portalRole', $role);
        view()->share('parentChildren', $parentChildren);

        return $next($request);
    }
}
