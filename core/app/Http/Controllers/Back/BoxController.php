<?php

namespace App\Http\Controllers\Back;

use App\{
    Models\Box,
    Repositories\Back\BcategoryRepository,
    Http\Requests\BoxRequest,
    Http\Controllers\Controller
};
use Illuminate\Http\Request;

class BoxController extends Controller
{
    /**
     * Constructor Method.
     *
     * Setting Authentication
     *
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('adminlocalize');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('back.box.index',[
            'datas' => Box::orderBy('id','desc')->get()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('back.box.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(BoxRequest $request)
    {
        $box = Box::create($request->all());
        if (isset($box->id)) {
            return redirect()->route('back.box.index')->withSuccess(__('New Box Added Successfully.'));
        }
        return redirect()->route('back.box.index')->withError(__('Something went wrong, Please try again later.'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Box  $box
     * @return \Illuminate\Http\Response
     */
    public function show(Box $box)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Box  $box
     * @return \Illuminate\Http\Response
     */
    public function edit(Box $box)
    {
        return view('back.box.edit',compact('box'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Box  $box
     * @return \Illuminate\Http\Response
     */
    public function update(BoxRequest $request, Box $box)
    {
        $box->update($request->all());
        return redirect()->route('back.box.index')->withSuccess(__('Category Updated Successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Box  $box
     * @return \Illuminate\Http\Response
     */
    public function destroy(Box $box)
    {
        if ($box->delete()) {
            return redirect()->route('back.box.index')->withSuccess('Box was deleted successfully.');
        } else {
            return redirect()->route('back.box.index')->withError('Something went wrong, Please try again later.');
        }
    }
}
