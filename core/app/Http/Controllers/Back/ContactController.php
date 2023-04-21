<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $datas = Contact::paginate(100);
        return view('back.contact.index', compact('datas'));
    }

    public function delete($id)
    {
        $contact = Contact::findOrFail($id);
        if ($contact->delete()) {
            return back()->with('success', 'Contact us message was delete successfully.');
        }
    }
}
