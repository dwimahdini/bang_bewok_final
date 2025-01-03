<?php


namespace App\Http\Controllers;

use App\Models\KeranjangPesanan;
use Carbon\Carbon;
use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class ProdukController extends Controller
{

    public function index()
    {
        // Periksa apakah role pengguna adalah 'admin' atau 'manajer'
        if (Auth::user()->role != 'admin' && Auth::user()->role != 'manajer') {
            abort(404); // Tampilkan halaman 404 jika role tidak sesuai
        }

        $produk = Produk::all()->map(function ($item) {
            $item->tanggal_kadaluarsa = Carbon::parse($item->tanggal_kadaluarsa);
            return $item;
        });

        // Hitung produk yang mendekati kadaluwarsa (misalnya dalam 30 hari)
        $produkMenipisKadaluarsa = $produk->filter(function ($item) {
            return $item->tanggal_kadaluarsa->diffInDays(Carbon::now()) <= 30;
        });

        // Hitung kategori produk yang tersedia
        $tersedia = $produk->where('status_tersedia', 'tersedia')->count();
        $menipis = $produk->where('status_tersedia', 'menipis')->count();
        $tidakTersedia = $produk->where('status_tersedia', 'tidak tersedia')->count();

        return view('inventori', compact('tersedia', 'menipis', 'tidakTersedia', 'produk', 'produkMenipisKadaluarsa'));
    }

    public function show($id)
    {
        $produk = Produk::findOrFail($id);
        return response()->json($produk);
    }

    public function updateProduk(Request $request, $id)
    {
        $request->validate([
            'nama_produk' => 'required|string|max:255',
            'jumlah' => 'required|integer|min:0',
            'harga' => 'required|numeric|min:0.01',
            'satuan' => 'required|string|max:255',
            'tanggal_kadaluarsa' => 'required|date',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $produk = Produk::findOrFail($id);
        $produk->nama_produk = $request->nama_produk;
        $produk->jumlah = $request->jumlah;
        $produk->harga = $request->harga;
        $produk->satuan = $request->satuan;
        $produk->tanggal_kadaluarsa = $request->tanggal_kadaluarsa;

        if ($request->hasFile('gambar')) {
            $imagePath = $request->file('gambar')->store('images', 'public');
            $produk->gambar = $imagePath;
        }

        $produk->save();

        return redirect()->route('produk.index')->with('success', 'Produk berhasil diupdate!');
    }

    public function store(Request $request): RedirectResponse
    {
        // Validasi input
        $request->validate([
            'nama_produk' => 'required|string|max:255',
            'jumlah' => 'required|integer|min:0',
            'harga' => 'required|numeric|min:0',
            'satuan' => 'required|string|max:50',
            'tanggal_kadaluarsa' => 'required|date',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    
        try {
            // Membuat entri baru di tabel Produk
            $produk = new Produk();
            $produk->fill($request->all());
    
            // Logika penyimpanan gambar
            if ($request->hasFile('gambar')) {
                $filename = $request->nama_produk . '-' . now()->timestamp . '.' . $request->file('gambar')->getClientOriginalExtension();
                $request->file('gambar')->move('img/', $filename); // Pindahkan file ke folder 'foto'
                $produk->gambar = $filename; // Simpan nama file ke field 'gambar'
            }
    
            $produk->save();
    
            return redirect()->route('produk.index')->with('status', 'Produk berhasil ditambahkan!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menambahkan produk: ' . $e->getMessage());
        }
    }
    
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'quantity' => 'required|integer',
        ]);

        $produk = Produk::findOrFail($id);

        // Update the product quantity
        $produk->jumlah += $validatedData['quantity']; // Adjust quantity based on the request
        $produk->save();

        return response()->json(['message' => 'Produk berhasil diperbarui'], 200);
    }

    public function edit($id): JsonResponse
    {
        $keranjang = KeranjangPesanan::with('produk')->findOrFail($id);
        return response()->json($keranjang);
    }
    /**
     * Delete a product by ID.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy($id)
    {
        $produk = Produk::findOrFail($id);
        if ($produk->gambar && Storage::disk('public')->exists($produk->gambar)) {
            Storage::disk('public')->delete($produk->gambar);
        }

        $produk->delete();

        return redirect()->route('produk.index')->with('status', 'Produk berhasil dihapus!');
    }

    public function berandaAdmin()
    {
        $produk = Produk::all();

        $produkTersedia = $produk->where('jumlah', '>=', 2)->count();
        $produkMenipis = $produk->where('jumlah', '<', 2)->count();
        $produkTidakTersedia = $produk->where('jumlah', 0)->count();
        $produkKedalursa = $produk->filter(function ($p) {
            return Carbon::parse($p->tanggal_kadaluarsa)->isPast();
        })->count();
        $produkMendekati = $produk->filter(function ($p) {
            return Carbon::parse($p->tanggal_kadaluarsa)->diffInDays(Carbon::now()) <= 3 && Carbon::parse($p->tanggal_kadaluarsa)->isFuture();
        })->count();
        $produkAman = $produk->filter(function ($p) {
            return Carbon::parse($p->tanggal_kadaluarsa)->diffInDays(Carbon::now()) > 3;
        })->count();

        return view('beranda', compact('produkTersedia', 'produkMenipis', 'produkTidakTersedia', 'produkKedalursa', 'produkMendekati', 'produkAman', 'produk'));
    }

    public function batal($id)
    {
        // Temukan produk berdasarkan ID
        $produk = Produk::find($id);

        // Temukan item keranjang yang sesuai
        $itemKeranjang = KeranjangPesanan::where('produk_id', $id)->first();

        if ($itemKeranjang) {
            // Kembalikan jumlah produk
            $produk->jumlah += $itemKeranjang->jumlah; // Misalnya, menambah jumlah produk
            $produk->save();

            // Hapus item dari keranjang
            $itemKeranjang->delete();
        }

        return redirect()->back()->with('sukses', 'Jumlah produk berhasil dikembalikan.');
    }
}
