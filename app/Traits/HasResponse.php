<?php
namespace App\Traits;

trait HasResponse{
  /**
   * Return basic response
   *
   * @param  string  $message default message that will returned
   * @param  bool  $success status of process
   * @param  array  $data that return with
   * @return array
   */
  private static function response(string|array $message = 'OK',bool $success = true,array $data = []): array{
    if(is_array($message)){
      $data = $message;
      $message = 'OK';
      if(empty($data)){
       $message = 'Data tidak ditemukan!';
       $success = false;
      }
    }

    return ['success' => $success,'message' => $message,'data' => $data];
  }
  private static function httpCode(int|string $code){
    return in_array($code,[401,403,422]) ? $code : 421;
  }
  public function display(array $data){
    return self::response($data);
  }
}
