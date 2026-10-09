{* File inputs of the ticket forms; Twenty-One's script adds more on "Add more". *}
<div class="form-group">
    <label for="inputAttachment1">{lang key='supportticketsticketattachments'}</label>
    <div class="input-group mb-1 attachment-group">
        <div class="custom-file">
            <label class="custom-file-label text-truncate" for="inputAttachment1" data-default="Choose file">{lang key='chooseFile'}</label>
            <input type="file" class="custom-file-input" name="attachments[]" id="inputAttachment1">
        </div>
        <div class="input-group-append">
            <button class="btn btn-default" type="button" id="btnTicketAttachmentsAdd">
                <i class="fas fa-plus"></i> {lang key='addmore'}
            </button>
        </div>
    </div>
    <div class="file-upload w-hidden">
        <div class="input-group mb-1 attachment-group">
            <div class="custom-file">
                <label class="custom-file-label text-truncate">{lang key='chooseFile'}</label>
                <input type="file" class="custom-file-input" name="attachments[]">
            </div>
        </div>
    </div>
    <div id="fileUploadsContainer"></div>
    <small class="text-muted">{lang key='supportticketsallowedextensions'}: {$allowedfiletypes} ({lang key="maxFileSize" fileSize="$uploadMaxFileSize"})</small>
</div>
