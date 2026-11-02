<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/* REMARK
 * Tujuan       : E-mail pemberitahuan kunjungan perawatan AC ke vendor (teks polos). Isi dibuat AcMessage::vendor; dikirim HANYA saat petugas menekan Kirim di layar Kunjungan AC (AcVisits::send).
 * Output       : surel teks dengan judul "Jadwal perawatan AC ...".
 * Dipakai oleh : App\Services\AcVisits.
 * Status       : Aktif.
 */
class AcVisitNotice extends Mailable
{
    public function __construct(public string $subjectText, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectText);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.ac-visit');
    }
}
