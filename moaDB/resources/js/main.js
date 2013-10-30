/*
* MoaDB Copyright (c) 2013
* Licensed under the GPL Version 3 license.
* *Version 1.0.0
*
*/
$(function() {
	$('[data-popup]').click(function(e){
		e.preventDefault;
		$('#modal #btn-main').show();
		if($(this).attr('data-title'))
			$('#modal .modal-title').text($(this).attr('data-title'));
		if($(this).attr('data-body'))
			$('#modal .modal-body').html($($(this).attr('data-body')).html());
		if($(this).attr('data-button'))
			if($(this).attr('data-button') === 'hidden')
				$('#modal #btn-main').hide();
			else
				$('#modal #btn-main').text($(this).attr('data-button'));
		$('#modal').modal('show');
	});

	$('#modal #btn-main').click(function(e){
		var m = $('#modal .modal-body');
		var f = m.find('form');
		switch(m.find('[data-type]').attr('data-type')){
			case 'object' :
				if(m.find('.alert-warning').hasClass('hidden') &&
					m.find('.alert-danger').hasClass('hidden')){
					var obj = $('#modal #newObj').val();
					var query = obj;
					if (query.substring(0, 1) !== "{") query = '{' + query;
					if (query.substring(query.length-1, query.length) !== "}") query = query + '}';
					try{
						var objectvalid = JSON.parse(query);
					}catch(e){
						if(e.message == "Unexpected token '"){
							m.find('.alert-warning').removeClass('hidden');
							m.find('.swopquote').click(function(){
								$('#modal #newObj').val($('#modal #newObj').val().replace(/\'/g, '"'));
								m.find('.alert-warning').addClass('hidden');
							});
						}
						else{
							m.find('.alert-danger .string').text(obj.substring(0, 4) + '...');
							m.find('.alert-danger').removeClass('hidden');
							m.find('.objectclose').click(function(){
								m.find('.alert-danger').addClass('hidden');
							});
						}
						return;
					}
				}
				break;
			case 'collection' :
				break;
			case 'database' :
				break;
			case 'sort' :
				document.location = "<?= $baseUrl . '?' . http_build_query($sortGet) . '&sort=' ?>" + $('#modal #sort').val() + '&sortdir=' + $('#modal #sortdir').val();
				return;
			case 'search' :
				document.location = "<?= $baseUrl . '?' . http_build_query($searchGet) . '&search=' ?>" + $('#modal #search').val() + '&searchField=' + $('#modal #searchField').val();
				return;
			case 'removeQuery' :
				var removequery = $('#modal #removeQuery').val();
				if (removequery.substring(0, 1) !== "{") removequery = '{' + removequery;
				if (removequery.substring(removequery.length-1, removequery.length) !== "}") removequery = removequery + '}';
				try{
					var queryvalid = JSON.parse(removequery);
					$('#modal #removeQuery').val(removequery);
				}catch(e){
					if(e.message == "Unexpected token '"){
						m.find('.alert-warning').removeClass('hidden');
						m.find('.swopquote').click(function(){
							$('#modal #find').val($('#modal #find').val().replace(/\'/g, '"'));
							m.find('.alert-warning').addClass('hidden');
						});
					}
					else
						m.find('.alert-danger').removeClass('hidden');
					return;
				}
				break;
			case 'query' :
				var query = $('#modal #find').val();
				if (query.substring(0, 1) !== "{") query = '{' + query;
				if (query.substring(query.length-1, query.length) !== "}") query = query + '}';
				try{
					var queryvalid = JSON.parse(query);
				}catch(e){
					if(e.message == "Unexpected token '"){
						m.find('.alert-warning').removeClass('hidden');
						m.find('.swopquote').click(function(){
							$('#modal #find').val($('#modal #find').val().replace(/\'/g, '"'));
							m.find('.alert-warning').addClass('hidden');
						});
					}
					else
						m.find('.alert-danger').removeClass('hidden');
					return;
				}
				document.location = "<?= $baseUrl . '?' . http_build_query($queryGet) . '&find=' ?>" + query;
				return;
								
		}
		if(f.length) 
			f.submit();

	});
	
	$('#modal .modal-body').keyup(function(){
		if($('.alert-danger', this).hasClass('hidden') && $('.alert-warning', this).hasClass('hidden')) 
			return;
		$('.alert', this).addClass('hidden');
	});
	$('#edit-button').click(function(){
		var type = $('.header li.active a').attr('data-view');
		switch(type) {
			case 'Databases':
				var d = $('.database');
				if(d.hasClass('edit'))
					$('.database .wiggle').ClassyWiggle('stop');
				else
					$('.database .wiggle').ClassyWiggle('start');
				d.toggleClass('click edit');
				d.find('.close').toggleClass('hidden');
				break;
			case 'Collections':
				var c = $('.collection');
				c.toggleClass('click edit');
				c.find('.shown').toggleClass('hidden');
				c.find('.close').toggleClass('hidden');
				break;
			case 'CollectionRow':
				$('#main-content table .close').parent('td').toggleClass('hidden');
				break;
			default :
				return;
		}
		$(this).toggleClass('open');
	});
	$('.marketing .database.click').click(function(){
		if(!$('.marketing .database.click').length) return;
		window.location.href = '?db=' + $(this).find('h4').html();
	});
	$('table.table-hover tr td:not(.noclick)').click(function(e){
		var $this = $(this).parents('tr');
		if($this.attr('data-ref')) return;
		$('.icon-caret-right, .icon-caret-down' ,$this).toggleClass('icon-caret-right icon-caret-down');
		$('table.table-hover tr[data-ref="'+$this.attr('id')+'"]').toggleClass('hidden');
	});
	$('.aotoggle').click(function() {
		var v = $(this).html();
		var pre = $(this).parents('td').find('pre');
		if(v === 'Obj View')
			$(this).html('Array View');
		else
			$(this).html('Obj View');
		pre.toggleClass('hidden');
		
	});
});