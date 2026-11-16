<?php

namespace App\Http\Controllers;

use App\Services\NaikKelasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class NaikKelasController extends Controller
{
    public function create(NaikKelasService $service)
    {
        $this->pastikanAdmin();
        try {
            $sumber = $service->sumberAktif();
        } catch (InvalidArgumentException $e) {
            return redirect()->route('dataPeriode.index')->with('naik_kelas_gagal', $e->getMessage());
        }

        return view('periode.naikKelas', [
            'sumber' => $sumber,
            'bolehSemester' => (int) $sumber->semester === 1,
        ]);
    }

    public function preview(Request $request, NaikKelasService $service)
    {
        $this->pastikanAdmin();
        $request->validate([
            'jenis' => 'required|in:semester,tahun_baru',
        ]);

        try {
            $data = $service->pratinjau($service->sumberAktif(), $request->jenis);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('dataPeriode.naikKelas')->with('naik_kelas_gagal', $e->getMessage());
        }

        return view('periode.naikKelasPratinjau', $data);
    }

    public function store(Request $request, NaikKelasService $service)
    {
        $this->pastikanAdmin();
        $request->validate([
            'jenis' => 'required|in:semester,tahun_baru',
            'aksi' => 'required|array',
            'aksi.*' => 'required|in:naik,tinggal,tetap,lulus',
            'kelas_tujuan' => 'nullable|array',
            'kelas_tujuan.*' => 'nullable|integer',
        ]);

        try {
            $hasil = $service->jalankan(
                $service->sumberAktif(),
                $request->jenis,
                $request->aksi,
                $request->input('kelas_tujuan', [])
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('dataPeriode.naikKelas')->with('naik_kelas_gagal', $e->getMessage());
        }

        return view('periode.naikKelasHasil', $hasil);
    }

    private function pastikanAdmin(): void
    {
        $user = Auth::user();
        if (!$user || !$user->role->contains('role', 'Administrator')) {
            abort(403);
        }
    }
}
