<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Post;
use App\Helpers\JwtAuth;

class PostController extends Controller {

    public function __construct() {

        $this->middleware('api.auth', ['except' => [
            'index', 
            'show', 
            'getImage',
            'getPostsByCategory',
            'getPostsByUser'
            ]]);
    }

    public function index() {

        $posts = Post::all()->load('category');

        return response()->json([
                    'code' => 200,
                    'status' => 'success',
                    'post' => $posts
                        ], 200);
    }

    public function show($id) {

        $post = Post::find($id)->load('category')
                               ->load('user');

        if (is_object($post)) {
            $data = [
                'code' => 200,
                'status' => 'success',
                'post' => $post
            ];
        } else {
            $data = [
                'code' => 404,
                'status' => 'error',
                'post' => 'la entrada no existe'
            ];
        }

        return response()->json($data, $data['code']);
    }

    public function store(Request $request) {

        //recoger datos por post
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);
        //identificar usuario
        if (!empty($params_array)) {
            $jwtAuth = new \JwtAuth();
            $token = $request->header('Authorization', null);
            $user = $jwtAuth->checkToken($token, true);
            //validar datos
            $validate = \Validator::make($params_array, [
                        'title' => 'required',
                        'content' => 'required',
                        'category_id' => 'required',
                        'estado' => 'required'
            ]);
            if ($validate->fails()) {
                $data = [
                    'code' => 400,
                    'status' => 'error',
                    'message' => 'No se a guardado el post'
                ];
            } else {
                //guardar post
                $post = new Post();
                $post->user_id = $user->sub;
                $post->category_id = $params->category_id;
                $post->title = $params->title;
                $post->content = $params->content;
                $post->image = $params->image;
                $post->estado = $params->estado;
                $post->save();

                $data = [
                    'code' => 200,
                    'status' => 'success',
                    'post' => $post
                ];
            }
        } else {
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'envia los datos correctos'
            ];
        }
        //devolver repuesta
        return response()->json($data, $data['code']);
    }

    public function update($id, Request $request) {
        //recoger
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);
        if (!empty($params_array)) {
            //validar
            $validate = \Validator::make($params_array, [
                        'title' => 'required',
                        'content' => 'required',
                        'category_id' => 'required'
            ]);
            if ($validate->fails()) {
                return response()->json($validate->errors(), 400);
            }
            //eliminar lo que no queremos actualizar
            unset($params_array['id']);
            unset($params_array['user_id']);
            unset($params_array['created_at']);
            unset($params_array['user']);
            //actualizar registro
            $where = [
                'id' => $id,
            ];

            $post = Post::updateOrCreate($where, $params_array);

            //devolver algo
            $data = array(
                'code' => 200,
                'status' => 'success',
                'post' => $post,
                'post' => $params_array
            );
        } else {
            $data = array(
                'code' => 400,
                'status' => 'error',
                'message' => 'datos enviados incorrectamente'
            );
        }
        return response()->json($data, $data['code']);
    }

    public function destroy($id, Request $request) {
        //conseguir usuario identificado
        $jwtAuth = new \JwtAuth();
        $token = $request->header('Authorization', null);
        $user = $jwtAuth->checkToken($token, true);


        //conseguir el reguistro
        $post = Post::where('id', $id)->where('user_id', $user->sub)->first();
        if (!empty($post)) {

            //borrarlo
            $post->delete();
            //devolver algo
            $data = [
                'code' => 200,
                'status' => 'success',
                'post' => $post
            ];
        } else {
            $data = [
                'code' => 404,
                'status' => 'error',
                'message' => 'el post no existe'
            ];
        }
        return response()->json($data, $data['code']);
    }

    public function upload(Request $request) {
        //recoger imagen
        $image = $request->file('file0');

        //validar datos
        $validate = \Validator::make($request->all(), [
                    'file0' => 'required|image|mimes:jpg,jpeg,png,gif'
        ]);
        //guardar imagen
        if (!$image || $validate->fails()) {
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'error al subir la imagen'
            ];
        } else {

            $image_name = time() . $image->getClientOriginalName();
            \Storage::disk('images')->put($image_name, \File::get($image));
            $data = [
                'code' => 200,
                'status' => 'success',
                'image' => $image_name
            ];
        }
        //devolver datos
        return response()->json($data, $data['code']);
    }

    public function getImage($filename) {
        //comprobar si existe
        $isset = \Storage::disk('images')->exists($filename);
        if ($isset) {
            //coseguir imagen
            $file = \Storage::disk('images')->get($filename);
            //devolver imagen
            return new Response($file, 200);
        } else {
            $data = [
                'code' => 404,
                'status' => 'error',
                'message' => 'la imagen no existe'
            ];
        }
        //monstar error
        return response()->json($data, $data['code']);
    }

    public function getPostsByCategory($id) {

        $posts = Post::where('category_id', $id)->get();

        return response() -> json([
                    'status' => 'success',
                    'posts' => $posts
                        ], 200);
    }
    
    public function getPostsByUser($id){
        $posts = Post::where('user_id',$id)->get();
        return response() -> json([
                    'status' => 'success',
                    'posts' => $posts
                        ], 200);
        
    }
    
}
