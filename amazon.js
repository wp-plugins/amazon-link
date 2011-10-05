var al_isOpera = (navigator.userAgent.indexOf('Opera') != -1);
var al_isIE = (!al_isOpera && navigator.userAgent.indexOf('MSIE') != -1);
var al_isNav = (navigator.appName.indexOf("Netscape") !=-1);
function al_handlerMM(e){
	if (!e) var e = window.event;
if (e.pageX || e.pageY)
	{
		al_x = e.pageX;
		al_y = e.pageY;
	}
else if (e.clientX || e.clientY)
	{
		al_x = e.clientX;
		al_y = e.clientY;
		if (al_isIE)
		{
			al_x += document.body.scrollLeft;
			al_y += document.body.scrollTop;
		}
	}
}

document.onmousemove = al_handlerMM;


al_x = 100;
al_y = 100;
al_timeout_ref=0;
al_timeout_in_ref=0;
al_overdiv=0;
al_overlink=0;
al_id=-1;

function al_div_out () {
   if (al_timeout_ref == 0) al_timeout_ref = setTimeout('al_timeout()',1000);
   al_overdiv = 0;
}

function al_div_in () {
   al_overdiv = 1;
   if (al_timeout_ref != 0) clearTimeout(al_timeout_ref);
   al_timeout_ref = 0;
}

function al_link_out () {
   if ((al_overdiv == 0) && (al_overlink == 1)) {
      if (al_timeout_ref == 0) al_timeout_ref = setTimeout('al_timeout()',1000);
      if (al_timeout_in_ref != 0) clearTimeout(al_timeout_in_ref);
      al_timeout_in_ref = 0;
   } 
   al_overlink = 0;

}

function al_link_in (id, content) {
   if ((id != al_id) || ((al_overlink == 0) && (al_overdiv == 0) && (al_timeout_ref == 0))) {
      al_content = content;
      if (al_timeout_in_ref == 0) setTimeout('al_show('+id+')',200)
   }
   if (al_timeout_ref!= 0) clearTimeout(al_timeout_ref);
   al_timeout_ref = 0;
   al_overlink = 1;

}

function al_timeout() {

   if ((al_overdiv == 0) && (al_overlink == 0) && (al_timeout_ref!= 0)) {
      al_timeout_ref=0;
      if (document.getElementById) { // DOM3 = IE5, NS6
         var menu_element = document.getElementById('al_popup');
         menu_element.style.visibility = 'hidden';
      }
   }
}

function al_show( id ) {

   if ((al_overlink == 1) || (al_overdiv ==1)) {
      if (al_timeout_ref!= 0) clearTimeout(al_timeout_ref);
      al_timeout_ref = 0;
      al_timeout_in_ref = 0;
      al_id = id;

      if (document.getElementById) { // DOM3 = IE5, NS6
         var menu_element = document.getElementById('al_popup');
         if (al_y> 10) al_y -= 5;
         al_x += 15;
         menu_element.style.left = al_x + "px";
         menu_element.style.top = al_y + "px";
         menu_element.style.visibility = 'visible';
         menu_element.innerHTML=al_content;
         menu_element.style.display = 'block';
      }
   } else {
      al_id = -1;
   }
}