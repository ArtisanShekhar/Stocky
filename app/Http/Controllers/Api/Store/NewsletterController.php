<?php

namespace App\Http\Controllers\Api\Store;

use Illuminate\Http\Request;
use App\Models\Subscriber;
use App\Http\Controllers\Controller;


class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:190|unique:subscribers,email',
        ]);

        $sub = Subscriber::create($data);

        return response()->json([
            'success' => true,
            'message' => __('Subscribed successfully'),
            'subscriber' => $sub,
        ]);
    }

}
