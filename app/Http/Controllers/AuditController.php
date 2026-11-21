<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(): View
    {
        return view('admin.audit', [
            'entries' => DB::table('audit_logs')->orderByDesc('id')->simplePaginate(25),
        ]);
    }

    public function hashes(Request $request): Response
    {
        $headers = ['Cache-Control' => 'private, no-store'];

        try {
            DB::table('audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'actor_level' => $request->user()->role_level,
                'action' => 'password_hashes.viewed',
                'result' => 'success',
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return response('La consulta de hashes no está disponible.', 503, $headers);
        }

        $users = User::where(fn ($query) => $query->where('must_change_password', false)->orWhereNotNull('temp_password_hash'))
            ->orderBy('id')->simplePaginate(25, ['id', 'password', 'crypto_version'])
            ->through(function (User $user): array {
                $hash = $user->password;
                $isRecognized = $user->crypto_version === 1
                    ? strlen($hash) === 128 && ctype_xdigit($hash)
                    : preg_match('~^\$argon2id\$v=\d+\$m=\d+,t=\d+,p=\d+\$[A-Za-z0-9+/]+\$[A-Za-z0-9+/]+$~D', $hash) === 1;

                return ['id' => $user->id, 'hash' => $isRecognized ? $hash : null];
            });

        return response()->view('admin.hashes', ['users' => $users], 200, $headers);
    }
}
