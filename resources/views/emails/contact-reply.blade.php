<x-mail::message>
# Hello {{ $contactMessage->name }},

Thank you for reaching out to us. We have reviewed your message and wanted to get back to you:

**Your original message:**
> {{ $contactMessage->message }}

---

**Our response:**
{{ $contactMessage->reply }}

If you have any further questions, feel free to reply to this email or contact us via WhatsApp support.

Thanks,<br>
**{{ config('app.name', 'Luca Glow') }} Team**
</x-mail::message>
