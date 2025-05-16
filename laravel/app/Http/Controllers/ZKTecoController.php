<?php
namespace App\Http\Controllers;


use Laradevsbd\Zkteco\Http\Library\zklib;

class ZKTecoController extends Controller
{
    protected $zk;

    public function __construct()
    {
        $this->zk = new zklib(env('ZKTECO_DEVICE_IP'), env('ZKTECO_DEVICE_PORT'));
    }

    public function connect()
    {
        if ($this->zk->connect()) {
            return "Kết nối thành công với máy chấm công";
        } else {
            return "Kết nối thất bại. Vui lòng kiểm tra lại IP và Port.";
        }
    }
    public function getUsers()
    {
        $this->zk->connect();

        $users = $this->zk->getUser();
        $this->zk->disconnect();

        return response()->json($users);
    }
    public function getAttendance()
    {
        $this->zk->connect();

        $attendance = $this->zk->getAttendance();
        $this->zk->disconnect();

        return response()->json($attendance);
    }
}
