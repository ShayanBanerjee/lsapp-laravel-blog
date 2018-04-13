<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Post;
use DB;

class PostsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth',['except' => ['index','show']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //$posts = Post::all();
        //$posts = Post::orderBy('title','asc')->take(1)->get();
        //$posts = Post::orderBy('title','asc')->get();
        //return Post::where('title','Post Two')->get();
        //$posts = DB::select('SELECT * FROM posts');
        //paginate limites the number of titles shown at a time
        //$posts = Post::orderBy('title','asc')->paginate(10);
        $posts = Post::orderBy('created_at','desc')->paginate(10);
        return view('posts.index')->with('posts',$posts);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('posts.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'title'=>'required',
            'body'=>'required',
            'cover_image'=>'image|nullable|max:1999'
        ]);

        // Handles File Upload
        if($request->hasFile('cover_image')){
            //Get filename with the extension
            $filenameWithExt = $request->file('cover_image')->getClientOriginalName();
            //Get just filename
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            // get just ext
            $extension = $request->file('cover_image')->getClientOriginalExtension();
            //Filename to Store
            $fileNameToStore = $filename.'_'.time().'.'.$extension;
            //Upload Image
            $path = $request->file('cover_image')->storeAs('public/cover_image',$fileNameToStore);
        } else {
            $fileNameToStore = 'noimage.jpg';
        }


        //create post
        $post = new Post;
        //enters title from input of the form
        $post->title = $request->input('title');
        //enters body of the text from the form submitted
        $post->body = $request->input('body');
        //taking user_id for recognise user posts in future
        $post->user_id = auth()->user()->id;
        //save the cover image
        $post->cover_image = $fileNameToStore;
        //save the input data
        $post->save();

        //need to redirect to the page containing the post data and success msg
        return redirect('/posts')->with('success','Post Created');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $post = Post::find($id);
        return view('posts.show')->with('post',$post);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $post = Post::find($id);
        //check for correct user
        if(auth()->user()->id !== $post->user_id){
            return redirect('/posts')->with('error','Unauthorized Page');
        }
        return view('posts.edit')->with('post',$post);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request,[
            'title'=>'required',
            'body'=>'required'
        ]);

        // Handles File Upload
        if($request->hasFile('cover_image')){
            //Get filename with the extension
            $filenameWithExt = $request->file('cover_image')->getClientOriginalName();
            //Get just filename
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            // get just ext
            $extension = $request->file('cover_image')->getClientOriginalExtension();
            //Filename to Store
            $fileNameToStore = $filename.'_'.time().'.'.$extension;
            //Upload Image
            $path = $request->file('cover_image')->storeAs('public/cover_image',$fileNameToStore);
        }

        //find a post here
        $post = Post::find($id);
        //check for correct user
        if(auth()->user()->id !== $post->user_id){
            return redirect('/posts')->with('error','Unauthorized Page');
        }
        //enters title from input of the form
        $post->title = $request->input('title');
        //enters body of the text from the form submitted
        $post->body = $request->input('body');
        //checking if image is stored
        if($request->hasFile('cover_image')){
            $post->cover_image = $fileNameToStore;
        }
        //save the input data
        $post->save();

        //need to redirect to the page containing the post data and success msg
        return redirect('/posts')->with('success','Post Updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $post = Post::find($id);
        //check for correct user
        if(auth()->user()->id !== $post->user_id){
            return redirect('/posts')->with('error','Unauthorized Page');
        }

        if($post->cover_image != 'noimage.jpg'){
            //Delete Image
            Storage::delete('public/cover_images/'.$post->cover_image);
        }
        $post->delete();
        return redirect('/posts')->with('success','Post Removed');
    }
}
