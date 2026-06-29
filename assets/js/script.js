(function($){
'use strict';
function esc(s){return $('<div>').text(s||'').html();}
function voterCard(v){
 var eligible=(v.eligible||'').toString().toUpperCase()==='YES'?'YES':esc(v.eligible||'');
 var voted=(v.voted||'').toString().toUpperCase()==='YES'?'YES':esc(v.voted||'');
 return '<div class="ghv-alert ghv-success"><strong>✅ Congratulations!</strong><br>Your name exists in the voter list.<br>Eligible Voter : '+esc(eligible)+'<br>No further action required.</div>'+
 '<div class="ghv-card"><h3>👤 '+esc(v.first_owner)+'</h3><div class="ghv-badges">'+
 '<span class="ghv-badge">🏢 Tower : '+esc(v.tower)+'</span>'+
 '<span class="ghv-badge">🚪 Flat : '+esc(v.flat)+'</span>'+
 '<span class="ghv-badge gray">🆔 Membership No : '+esc(v.membership_no)+'</span>'+
 '<span class="ghv-badge">🗳 Eligible Voter : '+esc(eligible)+'</span>'+
 '<span class="ghv-badge">✔ Previous Voting : '+esc(voted)+'</span></div>'+
 '<p><strong>Second Owner:</strong> '+esc(v.second_owner||'-')+'</p>'+
 '<div class="ghv-actions"><button class="ghv-btn ghv-btn-green ghv-download-card" data-id="'+esc(v.id)+'">Download Membership Card</button><button class="ghv-btn ghv-btn-dark ghv-show-correction" data-owner="'+esc(v.first_owner)+'" data-tower="'+esc(v.tower)+'" data-flat="'+esc(v.flat)+'" data-member="'+esc(v.membership_no)+'">Request Correction / Update Details</button></div></div>';
}
function applicationForm(q){
 return '<div class="ghv-alert ghv-error"><strong>❌ Name not found.</strong><br>Please apply for RWA Membership.</div><button class="ghv-btn ghv-btn-green ghv-toggle-application">Apply for Membership</button>'+
 '<form class="ghv-form ghv-application-form" enctype="multipart/form-data" style="display:none;margin-top:18px"><input type="hidden" name="action" value="ghv_submit_application"><input type="hidden" name="nonce" value="'+ghvRwa.nonce+'">'+
 '<div class="ghv-card"><h3>RWA Membership Application</h3><div class="ghv-grid">'+
 field('Full Name','full_name','text',q,true)+field('Mobile','mobile','tel','',true)+field('Email','email','email','',false)+field('Tower','tower','text','',true)+field('Flat','flat','text','',true)+
 field('Aadhaar Number','aadhaar_number','text','',true)+file('Upload Aadhaar Front','aadhaar_front',true)+file('Upload Aadhaar Back','aadhaar_back',true)+'</div>'+bank()+
 '<div class="ghv-grid">'+field('Transaction ID','transaction_id','text','',true)+file('Upload Receipt','payment_receipt',true)+'</div><div class="ghv-field"><label><input type="checkbox" name="declaration" value="1" required> I declare that the information submitted is true and request RWA membership.</label></div><button class="ghv-btn" type="submit">Submit Application</button><div class="ghv-form-result"></div></div></form>';
}
function correctionForm(){
 return '<form class="ghv-form ghv-correction-form" enctype="multipart/form-data" style="display:none;margin-top:18px"><input type="hidden" name="action" value="ghv_submit_correction"><input type="hidden" name="nonce" value="'+ghvRwa.nonce+'"><input type="hidden" name="membership_no"><input type="hidden" name="old_name"><input type="hidden" name="old_tower"><input type="hidden" name="old_flat">'+
 '<div class="ghv-card"><h3>Correction / Update Details Request</h3><p class="ghv-muted">Use this form only if your existing voter record needs correction.</p><div class="ghv-grid">'+
 field('Correct Full Name','full_name','text','',true)+field('Mobile','mobile','tel','',true)+field('Email','email','email','',false)+field('Tower','tower','text','',true)+field('Flat','flat','text','',true)+field('Aadhaar Number','aadhaar_number','text','',true)+file('Upload Aadhaar Proof','aadhaar_front',true)+file('Additional Proof','aadhaar_back',false)+'</div><div class="ghv-field"><label>Correction Details</label><textarea name="correction_notes" rows="4" required></textarea></div><button class="ghv-btn" type="submit">Submit Update Request</button><div class="ghv-form-result"></div></div></form>';
}
function field(label,name,type,value,req){return '<div class="ghv-field"><label>'+label+(req?' *':'')+'</label><input type="'+type+'" name="'+name+'" value="'+esc(value||'')+'" '+(req?'required':'')+'></div>';}
function file(label,name,req){return '<div class="ghv-field"><label>'+label+(req?' *':'')+'</label><input type="file" name="'+name+'" accept="image/*,.pdf" '+(req?'required':'')+'></div>';}
function bank(){return '<div class="ghv-bank"><h4>Membership Fee: ₹1100</h4><p><strong>GLOBAL HILL VIEW APARTMENT OWNERS WEL AS</strong></p><p><strong>Bank:</strong> HDFC Bank<br><strong>A/C:</strong> 50200061399909<br><strong>IFSC:</strong> HDFC0003648<br><strong>Branch:</strong> JMD Megapolis Sohna Road</p></div>';}
$(document).on('submit','#ghv-search-form',function(e){e.preventDefault();var form=$(this), q=$.trim(form.find('[name=q]').val()), out=$('#ghv-results'); if(!q){out.html('<div class="ghv-alert ghv-error">Please enter name, tower, or flat number.</div>');return;} form.addClass('ghv-loading'); out.html('<div class="ghv-alert ghv-info">Searching...</div>'); $.post(ghvRwa.ajaxurl,{action:'ghv_search_voters',nonce:ghvRwa.nonce,q:q},function(res){form.removeClass('ghv-loading'); if(!res.success){out.html('<div class="ghv-alert ghv-error">Search failed.</div>');return;} if(res.data.found){var html=''; $.each(res.data.voters,function(i,v){html+=voterCard(v);}); html+=correctionForm(); out.html(html);}else{out.html(applicationForm(q));}},'json');});
$(document).on('click','.ghv-toggle-application',function(){$('.ghv-application-form').slideToggle();});
$(document).on('click','.ghv-show-correction',function(){var b=$(this), f=$('.ghv-correction-form'); f.find('[name=old_name]').val(b.data('owner')); f.find('[name=old_tower]').val(b.data('tower')); f.find('[name=old_flat]').val(b.data('flat')); f.find('[name=membership_no]').val(b.data('member')); f.find('[name=full_name]').val(b.data('owner')); f.find('[name=tower]').val(b.data('tower')); f.find('[name=flat]').val(b.data('flat')); f.slideDown(); $('html,body').animate({scrollTop:f.offset().top-80},400);});
$(document).on('submit','.ghv-form',function(e){e.preventDefault();var f=$(this), data=new FormData(this), result=f.find('.ghv-form-result'); f.addClass('ghv-loading'); result.html('<div class="ghv-alert ghv-info">Submitting...</div>'); $.ajax({url:ghvRwa.ajaxurl,type:'POST',data:data,processData:false,contentType:false,success:function(res){f.removeClass('ghv-loading'); if(res.success){result.html('<div class="ghv-alert ghv-success">'+esc(res.data.message)+'</div>'); f[0].reset();}else{result.html('<div class="ghv-alert ghv-error">'+esc((res.data&&res.data.message)||'Submission failed')+'</div>');}},error:function(){f.removeClass('ghv-loading'); result.html('<div class="ghv-alert ghv-error">Submission failed.</div>');}});});
$(document).on('click','.ghv-download-card',function(){window.location=ghvRwa.ajaxurl+'?action=ghv_download_card&nonce='+encodeURIComponent(ghvRwa.nonce)+'&id='+encodeURIComponent($(this).data('id'));});
})(jQuery);
