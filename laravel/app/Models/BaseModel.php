<?php
namespace App\Models;
use Carbon\Carbon;
use DateTime;
use DateTimeInterface;
use Eloquent;
use Illuminate\Support\Str;

class BaseModel extends Eloquent  {



    // Sử dụng convert dịnh dạng date time chuẩn cho API
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }



	public function setCreatedAtAttribute($value) {


		if($this->verifyDate($value,'d/m/Y H:i:s')){
			$this->attributes['created_at']=Carbon::createFromFormat('d/m/Y H:i:s', $value);
		}
		else
		{
			$this->attributes['created_at']=Carbon::now();
		}

	}

    public function setEndedAtAttribute($value) {


        if($this->verifyDate($value,'d/m/Y H:i:s')){
            $this->attributes['ended_at']=Carbon::createFromFormat('d/m/Y H:i:s', $value);;
        }
        else
        {
            $this->attributes['ended_at']=Carbon::now();
        }

    }

    // public function setSlugAttribute($value) {

    //     $this->attributes['slug']= Str::slug($value, '-');

    // }


	function verifyDate($value,$format){
		return (DateTime::createFromFormat($format, $value) !== false);
	}
}
