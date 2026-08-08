<p>{{ __('Hello') }} {{ $user->detail?->first_name ?? $user->username }},</p>

<p>{{ __('You are invited to vote on:') }} <strong>{{ $vote->title }}</strong></p>

@if($vote->description)
<p>{{ $vote->description }}</p>
@endif

<p><a href="{{ $url }}" style="display:inline-block;padding:12px 24px;background:#006699;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold">{{ __('Vote Now') }}</a></p>

<p style="font-size:12px;color:#888">{{ __('This link is personal and should not be shared.') }}</p>
