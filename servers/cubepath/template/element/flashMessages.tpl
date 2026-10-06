<div class="row">
	{if isset($flashMessages) && $flashMessages}
		{foreach from=$flashMessages item=message}
			<div class="alert alert-{$message['type']}" role="alert">
				<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
				{$message['message']}
			</div>
		{/foreach}
	{/if}
</div>
