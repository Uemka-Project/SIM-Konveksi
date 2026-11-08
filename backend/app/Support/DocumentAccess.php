<?php

namespace App\Support;

use App\Models\User;

/**
 * Per-document-type access rules for the printable FOD documents (FR-O5).
 *
 * Every role may read the "documents" module, but the PRD (section 7) limits
 * each document type to the roles that work with the underlying data:
 * production receives the SPK and production report, the warehouse receives
 * purchase notes and SPKs, and only owner/admin may print sales documents.
 */
final class DocumentAccess
{
    /**
     * @var array<string, list<string>>
     */
    public const TYPES = [
        'nota-pesanan' => ['owner', 'admin'],
        'bukti-bayar' => ['owner', 'admin'],
        'nota-pembelian' => ['owner', 'admin', 'gudang'],
        'spk' => ['owner', 'admin', 'produksi', 'gudang'],
        'hasil-produksi' => ['owner', 'admin', 'produksi'],
        'surat-jalan' => ['owner', 'admin'],
    ];

    public static function isKnown(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function allows(User $user, string $type): bool
    {
        if (! self::isKnown($type)) {
            return false;
        }

        if (! AccessMatrix::can($user, 'documents', 'R')) {
            return false;
        }

        $user->loadMissing('roles');

        foreach ($user->roles as $role) {
            if (in_array($role->role_name, self::TYPES[$type], true)) {
                return true;
            }
        }

        return false;
    }
}
