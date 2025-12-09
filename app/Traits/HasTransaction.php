<?php
namespace App\Traits;

use App\Traits\HasResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HasTransaction{
  use HasResponse;

  public function handle(string $type,callable $handle,callable $successCallback = null){
    try {
        DB::beginTransaction();

        $data = $handle();

        if(is_callable($successCallback)){
          $response = $successCallback($data);
        }else{
          if($type == 'store') $response = self::response('Simpan data berhasil');
          else if($type == 'update') $response = self::response('Ubah data berhasil');
          else if($type == 'destroy') $response = self::response('Hapus data berhasil');
          else $response = self::response($type.' berhasil!');
        }

        $httpCode = 200;
        DB::commit();
    } catch (Throwable $e) {
        DB::rollback();

        $httpCode = self::httpCode($e->getCode());

        if($httpCode == 421 && config('app.env') == 'production') $msg = 'Terjadi kesalahan di server';
        else $msg = $e->getMessage(); //.' '.$e->getFile().'('.$e->getLine().')';
        Log::info($e->getMessage().': '.$e->getFile().'('.$e->getLine().')');

        $response = self::response($msg,false);
    }
    return response()->json($response,$httpCode);
  }
}
