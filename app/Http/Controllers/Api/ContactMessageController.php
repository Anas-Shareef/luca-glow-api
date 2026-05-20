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

        // Set properties temporarily on the model instance
        $message->reply = $validated['reply'];
        $message->replied_at = now();

        // Send email first
        try {
            Mail::to($message->email)->send(new ContactReplyMail($message));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply email. Please check your SMTP configuration. Error: ' . $e->getMessage(),
            ], 500);
        }

        // Save to database only on successful email delivery
        $message->save();

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully!',
            'data' => $message
        ]);
    }
}
