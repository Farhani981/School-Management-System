<?php

namespace App\Services;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

class StudentIdCards
{
    public function download($students, string $filename)
    {
        $cards = $students->map(function (Student $student) {
            $photo = null;
            if ($student->photo && Storage::disk('public')->exists($student->photo)) {
                // A small print thumbnail keeps a batch of 10 MB uploads out of the PDF document's memory.
                $source = @imagecreatefromstring(Storage::disk('public')->get($student->photo));
                if ($source !== false) {
                    $thumbnail = imagecreatetruecolor(160, 160);
                    imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 255, 255, 255));
                    $size = min(imagesx($source), imagesy($source));
                    imagecopyresampled($thumbnail, $source, 0, 0, (int) ((imagesx($source) - $size) / 2), (int) ((imagesy($source) - $size) / 2), 160, 160, $size, $size);
                    ob_start();
                    imagejpeg($thumbnail, null, 85);
                    $photo = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
                    imagedestroy($source);
                    imagedestroy($thumbnail);
                }
            }
            $qr = (new QRCode(new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, 'scale' => 5])))->render(route('students.show', $student));
            return compact('student', 'photo', 'qr');
        });
        return Pdf::loadView('students.pdf.id-cards', ['cards' => $cards, 'options' => \App\Http\Controllers\StudentRecordsController::options()])->setPaper('a4')->download($filename);
    }
}
