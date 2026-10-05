{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">VNC Console</h3>
			</div>
			<div class="panel-body">
				<div class="alert alert-info">
					{if isset($message) && $message}
						{$message}
					{else}
						VNC console access is not yet available via the API. Please check back later.
					{/if}
				</div>
			</div>
		</div>
	</div>
</div>
