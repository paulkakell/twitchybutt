<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\TokenAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PostController
{
    public function index(): View
    {
        return view('catalog', ['posts' => Post::query()->where('status', 'published')->where('classification', 'general')->latest('id')->paginate(12, ['id', 'title', 'price_units', 'created_at'])]);
    }

    public function show(Post $post): View
    {
        // Fail with 404 for drafts and restricted posts, including their titles.
        if (! Gate::allows('manage-content')) {
            abort_unless($post->status === 'published' && $post->classification === 'general', 404);
        }

        return view('post', ['post' => $post, 'canRead' => Gate::allows('view', $post)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-content');
        $post = Post::query()->create($this->validated($request));
        Log::info('cms.post.created', ['post_id' => $post->id, 'actor_id' => $request->user()?->getAuthIdentifier()]);

        return redirect('/studio')->with('status', 'Post saved.');
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('manage-content');
        $post->fill($this->validated($request))->save();
        Log::info('cms.post.updated', ['post_id' => $post->id, 'actor_id' => $request->user()?->getAuthIdentifier()]);

        return redirect('/studio')->with('status', 'Post updated.');
    }

    /** @return array{title: string, body: string, classification: string, status: string, price_units: int} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:50000'],
            'classification' => ['required', Rule::in(['general', 'restricted', 'unclassified'])],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'price' => ['required', 'string', 'max:14'],
        ]);
        if ($data['status'] === 'published' && $data['classification'] !== 'general') {
            throw ValidationException::withMessages(['classification' => 'Restricted and unclassified posts must remain drafts in this release.']);
        }
        try {
            $units = TokenAmount::parse($data['price']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['price' => $exception->getMessage()]);
        }

        return ['title' => $data['title'], 'body' => $data['body'], 'classification' => $data['classification'], 'status' => $data['status'], 'price_units' => $units];
    }
}
