<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\KeranjangPesanan;
use Illuminate\Http\Request;

class PesanProdukController extends Controller
{
    public function index()
    {
        $produk = Produk::all();
        return view('pesanProduk', compact('produk'));
    }

    public function tambahKeKeranjang(Request $request)
    {
        $request->validate([
            'productId' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        // Temukan produk berdasarkan ID
        $produk = Produk::findOrFail($request->productId);

        // Periksa apakah jumlah yang diminta tersedia
        if ($produk->jumlah < $request->quantity) {
            return response()->json(['error' => 'Jumlah produk tidak cukup.'], 400);
        }

        // Kurangi jumlah produk
        $produk->jumlah -= $request->quantity;
        $produk->save();

        // Tambahkan produk ke keranjang
        KeranjangPesanan::create([
            'produk_id' => $produk->id,
            'jumlah' => $request->quantity,
        ]);

        return response()->json(['success' => 'Produk berhasil dipesan.']);
    }
}
