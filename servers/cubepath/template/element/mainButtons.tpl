<div class="row" style="margin-bottom: 20px;">
	<div class="btn-group btn-group-justified col-sm-12">
		<a href="clientarea.php?action=productdetails&id={$serviceid}"
		   class="btn btn-{if $controller=='Main'}info{else}primary{/if}"
		   type="button">Overview</a>
		<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Backups"
		   class="btn btn-{if $controller=='Backups'}info{else}primary{/if}"
		   type="button">Backups</a>
		<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Dns"
		   class="btn btn-{if $controller=='Dns'}info{else}primary{/if}"
		   type="button">DNS</a>
		<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Sshkeys"
		   class="btn btn-{if $controller=='Sshkeys'}info{else}primary{/if}"
		   type="button">SSH Keys</a>
		<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Firewall"
		   class="btn btn-{if $controller=='Firewall'}info{else}primary{/if}"
		   type="button">Firewall</a>
		<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Oschange"
		   class="btn btn-{if $controller=='Oschange'}info{else}primary{/if}"
		   type="button">Reinstall</a>
		<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Isochange"
		   class="btn btn-{if $controller=='Isochange'}info{else}primary{/if}"
		   type="button">ISO</a>
		<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Console"
		   class="btn btn-{if $controller=='Console'}info{else}primary{/if}"
		   type="button">Console</a>
	</div>
</div>
