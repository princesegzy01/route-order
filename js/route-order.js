 (function ($) {    
 	// Set Datatable
	jQuery('#ordertable').dataTable({    
	    "bSort": true,  
	    "order": [[ 7, "desc" ]],
	    "bPaginate": true,
	    "bLengthChange": true,
	    "bFilter": true,
	    "bInfo": true,
	    "bAutoWidth": true, 
	    "sDom": 'T<"panel-menu dt-panelmenu"lfr><"clearfix">tip',
	    "oTableTools": {
    	  "sSwfPath": "<a href='//cdnjs.cloudflare.com/ajax/libs/datatables-tabletools/2.1.5/swf/copy_csv_xls_pdf.swf' target='_blank' rel='nofollow'>http://cdnjs.cloudflare.com/ajax/libs/datatables-tabletools/2.1.5/swf/copy_csv_xls_pdf.swf</a>",
	      // "sSwfPath": "http://cdnjs.cloudflare.com/ajax/libs/datatables-tabletools/2.1.5/swf/copy_csv_xls_pdf.swf",
	      "aButtons": [ 
	          "csv",
	          "xls",
	          {
	              "sExtends": "pdf",
	              "bFooter": true,
	              "sPdfMessage": "List of All Orders ",
	              "sPdfOrientation": "landscape"
	          },
	          "print"
	    ]}
	});  
	
	// Select All items in table
	jQuery(".select_all").click(function(){
		if(jQuery(this).prop("checked")){ 
			jQuery(".case").prop("checked",true);
		}else{ 
			jQuery(".case").prop("checked",false);	
		}
	});
 
}(jQuery)); //end document.ready






 