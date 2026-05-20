<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Mail\ContactReplyMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends Controller
{
    /**
     * Store a newly created contact message in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $message = ContactMessage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Your message has been received. We will get back to you soon!',
            'data' => $message
        ], 201);
    }

    /**
     * Display a listing of all contact messages (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user has permission or is admin (routes are guarded by auth:sanctum)
        $messages = ContactMessage::orderBy('created_at', 'desc')->get();

        return response()->json($messages);
    }

    /**
     * Send a reply to the submitter via email (Admin only).
     */
    public function reply(Request $request, ContactMessage $message): JsonResponse
    {
        $validated = $request->validate([
            'reply' => 'required|string|max:5000',
        ]);

        // Set properties and save to database first
        $message->reply = $validated['reply'];
        $message->replied_at = now();
        $message->save();

        // Send email first
        $emailSent = true;
        $errorMsg = null;
        try {
            Mail::to($message->email)->send(new ContactReplyMail($message));
        } catch (\Exception $e) {
            $emailSent = false;
            $errorMsg = $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'email_sent' => $emailSent,
            'message' => $emailSent
                ? 'Reply sent successfully!'
                : 'Reply saved, but failed to send email. Please check your SMTP configuration on Render. Error: ' . $errorMsg,
            'data' => $message
        ]);
    }

    /**
     * Delete a contact message (Admin only).
     */
    public function destroy(ContactMessage $message): JsonResponse
    {
        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully!'
        ]);
    }
}
