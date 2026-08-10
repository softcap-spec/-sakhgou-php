<?php
// chat_fab.php — Avito-style chat v4: avatars, typing, read receipts, status
$cu = auth_user();
if (!$cu) return;
$pdo = db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
$stmt->execute([$cu['id']]);
$unread_msgs = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$cu['id']]);
$unread_notifs = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$cu['id']]);
$notifs = $stmt->fetchAll();

// Chat list with last message + avatar + listing
$stmt = $pdo->prepare("
  SELECT m.*, l.title AS listing_title, l.id AS lid,
    u.name AS other_name, u.avatar_url AS other_avatar
  FROM messages m
  JOIN listings l ON m.listing_id = l.id
  JOIN users u ON IF(m.sender_id = ?, m.receiver_id, m.sender_id) = u.id
  WHERE (m.sender_id = ? OR m.receiver_id = ?)
  GROUP BY m.listing_id, IF(m.sender_id = ?, m.receiver_id, m.sender_id)
  ORDER BY m.created_at DESC LIMIT 20
");
$stmt->execute([$cu['id'], $cu['id'], $cu['id'], $cu['id']]);
$chats = $stmt->fetchAll();

$unreadByChat = [];
foreach ($chats as $c) {
  $other = ($c['sender_id'] == $cu['id']) ? $c['receiver_id'] : $c['sender_id'];
  $key = $c['lid'] . '_' . $other;
  if (!isset($unreadByChat[$key])) {
    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE listing_id=? AND sender_id=? AND receiver_id=? AND is_read=0');
    $stmt2->execute([$c['lid'], $other, $cu['id']]);
    $unreadByChat[$key] = (int)$stmt2->fetchColumn();
  }
}
?>
<style>
/* === Avito-style Chat Widget v4 === */
.chat-fab-container{position:fixed;bottom:1.25rem;right:1.25rem;z-index:90;display:flex;flex-direction:column;gap:0.625rem}
.chat-fab-btn{width:3rem;height:3rem;border-radius:50%;border:0;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(15,23,32,0.12);transition:transform .2s,box-shadow .2s;position:relative}
.chat-fab-btn:hover{transform:scale(1.06);box-shadow:0 6px 18px rgba(15,23,32,0.18)}
.chat-fab-btn:active{transform:scale(.95)}
.chat-fab-chat{background:#121E2B;color:#fff}
.chat-fab-bell{background:#fff;color:#121E2B;border:1px solid #DFE4EA}
.chat-fab-badge{position:absolute;top:-4px;right:-4px;background:#DC2626;color:#fff;font-size:.625rem;font-weight:700;min-width:1.125rem;height:1.125rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;padding:0 .25rem}

.chat-widget{position:fixed;bottom:4.5rem;right:1.25rem;z-index:91;width:24rem;height:32rem;max-height:calc(100vh - 6rem);background:#fff;border-radius:12px;box-shadow:0 20px 50px -10px rgba(15,23,32,0.2);display:none;flex-direction:column;overflow:hidden;border:1px solid #EBEEF2}
.chat-widget.open{display:flex}

.chat-widget-header{padding:.875rem 1rem;border-bottom:1px solid #EBEEF2;display:flex;align-items:center;justify-content:space-between;background:#fff;flex-shrink:0}
.chat-widget-title{font-size:.875rem;font-weight:600;color:#121E2B}
.chat-widget-close{width:1.75rem;height:1.75rem;border:0;background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:6px;color:#7A8A9A;transition:all .15s}
.chat-widget-close:hover{background:#F7F9FB;color:#121E2B}
.chat-widget-body{flex:1;overflow-y:auto}
.chat-widget-empty{padding:2rem 1rem;text-align:center;color:#9AAAB8;font-size:.8125rem}

/* Chat list — Avito style */
.chat-list-item{padding:.75rem 1rem;border-bottom:1px solid #F0F3F7;cursor:pointer;transition:background .15s;display:flex;gap:.625rem;align-items:center}
.chat-list-item:hover{background:#F7F9FB}
.chat-list-avatar{width:3rem;height:3rem;border-radius:50%;background:#EEF2F6;display:flex;align-items:center;justify-content:center;font-weight:700;color:#7A8A9A;font-size:.75rem;overflow:hidden;flex-shrink:0}
.chat-list-avatar img{width:100%;height:100%;object-fit:cover}
.chat-list-name{font-size:.8125rem;font-weight:600;color:#121E2B;line-height:1.2}
.chat-list-preview{font-size:.75rem;color:#7A8A9A;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:.125rem}
.chat-list-meta{font-size:.625rem;color:#9AAAB8;margin-top:.25rem;display:flex;align-items:center;gap:.375rem}
.chat-list-time{font-size:.625rem;color:#9AAAB8}
.chat-list-unread{background:#DC2626;color:#fff;font-size:.5625rem;font-weight:700;min-width:1rem;height:1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;padding:0 .25rem;flex-shrink:0;margin-left:auto}

/* Thread header with avatar */
.chat-thread{display:none;flex-direction:column;height:100%}
.chat-thread.active{display:flex}
.chat-thread-back{display:flex;align-items:center;gap:.5rem;padding:.625rem .875rem;border-bottom:1px solid #EBEEF2;cursor:pointer;background:#fff;flex-shrink:0}
.chat-thread-back:hover{background:#F7F9FB}
.chat-thread-avatar{width:2.25rem;height:2.25rem;border-radius:50%;background:#EEF2F6;display:flex;align-items:center;justify-content:center;font-weight:700;color:#7A8A9A;font-size:.625rem;overflow:hidden;flex-shrink:0}
.chat-thread-avatar img{width:100%;height:100%;object-fit:cover}
.chat-thread-name{font-weight:600;color:#121E2B;font-size:.8125rem;line-height:1.2}
.chat-thread-status{font-size:.6875rem;color:#9AAAB8}
.chat-thread-status.online{color:#16A34A}
.chat-thread-listing{font-size:.6875rem;color:#9AAAB8;margin-top:.125rem}
.chat-thread-back-arrow{color:#7A8A9A;flex-shrink:0}

/* Messages area */
.chat-messages{flex:1;overflow-y:auto;padding:.75rem;display:flex;flex-direction:column;gap:.375rem;background:#F7F9FB}
.chat-date-sep{text-align:center;font-size:.625rem;color:#9AAAB8;margin:.5rem 0}
.chat-msg-group{display:flex;gap:.5rem;align-items:flex-end;max-width:85%}
.chat-msg-group.mine{align-self:flex-end;flex-direction:row-reverse}
.chat-msg-group.theirs{align-self:flex-start}
.chat-msg-avatar{width:1.75rem;height:1.75rem;border-radius:50%;background:#EEF2F6;display:flex;align-items:center;justify-content:center;font-weight:700;color:#7A8A9A;font-size:.5rem;overflow:hidden;flex-shrink:0}
.chat-msg-avatar img{width:100%;height:100%;object-fit:cover}
.chat-msg-bubble{max-width:100%;padding:.5rem .75rem;border-radius:12px;font-size:.8125rem;line-height:1.4;word-wrap:break-word;position:relative}
.chat-msg-group.mine .chat-msg-bubble{background:#1B6B8A;color:#fff;border-bottom-right-radius:4px}
.chat-msg-group.theirs .chat-msg-bubble{background:#fff;color:#121E2B;border-bottom-left-radius:4px;box-shadow:0 1px 2px rgba(0,0,0,0.04)}
.chat-msg-meta{display:flex;align-items:center;gap:.25rem;margin-top:.125rem;justify-content:flex-end}
.chat-msg-time{font-size:.5625rem;color:#9AAAB8}
.chat-msg-group.mine .chat-msg-time{color:rgba(255,255,255,.6)}
.chat-msg-status{display:flex;gap:0}

/* Typing indicator */
.chat-typing{padding:.375rem .75rem .25rem;font-size:.6875rem;color:#7A8A9A;display:none;font-style:italic}
.chat-typing.active{display:block}

/* Input area */
.chat-input-area{border-top:1px solid #EBEEF2;padding:.625rem;display:flex;gap:.5rem;background:#fff;flex-shrink:0}
.chat-input{flex:1;border:1px solid #DFE4EA;border-radius:20px;padding:.5rem .875rem;font-size:.8125rem;outline:none;transition:border-color .15s;background:#F7F9FB}
.chat-input:focus{border-color:#1B6B8A;background:#fff}
.chat-send-btn{width:2.25rem;height:2.25rem;border:0;border-radius:50%;background:#1B6B8A;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0}
.chat-send-btn:hover{background:#155A75}
.chat-send-btn:disabled{background:#C8D0DA;cursor:not-allowed}

/* Notification panel */
.notif-panel{position:fixed;bottom:4.5rem;right:1.25rem;z-index:91;width:20rem;max-height:24rem;background:#fff;border-radius:12px;box-shadow:0 20px 50px -10px rgba(15,23,32,0.2);display:none;flex-direction:column;overflow:hidden;border:1px solid #EBEEF2}
.notif-panel.open{display:flex}
.notif-item{padding:.75rem 1rem;border-bottom:1px solid #F0F3F7;cursor:pointer;transition:background .15s}
.notif-item:hover{background:#F7F9FB}
.notif-text{font-size:.8125rem;color:#121E2B}
.notif-time{font-size:.6875rem;color:#9AAAB8;margin-top:.25rem}
</style>

<!-- FAB -->
<div class="chat-fab-container">
  <button class="chat-fab-btn chat-fab-bell" onclick="toggleNotif()" title="Уведомления">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <?php if ($unread_notifs > 0): ?><span class="chat-fab-badge"><?=$unread_notifs?></span><?php endif; ?>
  </button>
  <button class="chat-fab-btn chat-fab-chat" onclick="toggleChat()" title="Сообщения">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <?php if ($unread_msgs > 0): ?><span class="chat-fab-badge"><?=$unread_msgs?></span><?php endif; ?>
  </button>
</div>

<!-- Notification panel -->
<div id="notifPanel" class="notif-panel">
  <div class="chat-widget-header">
    <span class="chat-widget-title">Уведомления</span>
    <button class="chat-widget-close" onclick="toggleNotif()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
  </div>
  <div class="chat-widget-body">
    <?php if (empty($notifs)): ?><div class="chat-widget-empty">Нет уведомлений</div>
    <?php else: foreach($notifs as $n): ?>
      <div class="notif-item"<?=$n['link']?' onclick="window.location.href=\''.h($n['link']).'\'"':''?>><div class="notif-text"><?=h($n['text'])?></div><div class="notif-time"><?=time_ago($n['created_at'])?></div></div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Chat widget -->
<div id="chatWidget" class="chat-widget">
  <div id="chatListView" class="chat-thread active">
    <div class="chat-widget-header">
      <span class="chat-widget-title">Сообщения</span>
      <button class="chat-widget-close" onclick="toggleChat()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="chat-widget-body">
      <?php if (empty($chats)): ?>
        <div class="chat-widget-empty">Нет сообщений<br><br><a href="/catalog" class="text-accent font-medium hover:underline">Найти объявления</a></div>
      <?php else: foreach($chats as $c):
        $other = ($c['sender_id'] == $cu['id']) ? $c['receiver_id'] : $c['sender_id'];
        $key = $c['lid'].'_'.$other; $unr = $unreadByChat[$key] ?? 0;
        $isMine = ($c['sender_id'] == $cu['id']);
        $preview = ($isMine ? 'Вы: ' : '') . mb_substr($c['text'], 0, 50);
      ?>
        <div class="chat-list-item" onclick="openThread(<?=$c['lid']?>,<?=$other?>,'<?=h(addslashes($c['other_name']))?>','<?=h(addslashes($c['listing_title']))?>','<?=h(addslashes($c['other_avatar']??''))?>')">
          <div class="chat-list-avatar"><?php if($c['other_avatar']):?><img src="<?=h($c['other_avatar'])?>" alt=""><?php else:?><?=mb_substr($c['other_name'],0,2)?><?php endif;?></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="chat-list-name"><?=h($c['other_name'])?></span>
              <span class="chat-list-time"><?=time_ago($c['created_at'])?></span>
            </div>
            <div class="chat-list-preview"><?=h($preview)?></div>
            <div class="chat-list-meta"><?=h($c['listing_title'])?><?=$isMine?'<span style="color:#9AAAB8"> · ✓✓</span>':''?></div>
          </div>
          <?php if($unr>0):?><span class="chat-list-unread"><?=$unr?></span><?php endif;?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div id="chatThreadView" class="chat-thread">
    <div class="chat-thread-back" onclick="backToList()">
      <svg class="chat-thread-back-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      <div class="chat-thread-avatar" id="threadAvatar"></div>
      <div style="flex:1;min-width:0">
        <div class="chat-thread-name" id="threadName"></div>
        <div class="chat-thread-status" id="threadStatus"></div>
      </div>
    </div>
    <div class="chat-messages" id="chatMessages"></div>
    <div class="chat-typing" id="chatTyping">печатает...</div>
    <div class="chat-input-area">
      <input type="text" class="chat-input" id="chatInput" placeholder="Сообщение..." onkeydown="if(event.key==='Enter')sendMessage()" oninput="onTyping()">
      <button class="chat-send-btn" id="chatSendBtn" onclick="sendMessage()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
      </button>
    </div>
  </div>
</div>

<script>
var currentLid=0,currentUid=0,pollTimer=null,typingTimer=null,myUid=<?=json_encode($cu['id'])?>;
var otherAvatar='',otherName='';

function toggleChat(){var w=document.getElementById('chatWidget'),n=document.getElementById('notifPanel');n.classList.remove('open');w.classList.toggle('open');if(!w.classList.contains('open')){backToList();if(pollTimer){clearInterval(pollTimer);pollTimer=null}}}
function toggleNotif(){var n=document.getElementById('notifPanel'),w=document.getElementById('chatWidget');w.classList.remove('open');n.classList.toggle('open')}

function openThread(lid,uid,name,listing,avatar){
 currentLid=lid;currentUid=uid;otherName=name;otherAvatar=avatar;
 document.getElementById('threadName').textContent=name;
 document.getElementById('threadStatus').textContent='';
 var av=document.getElementById('threadAvatar');
 if(avatar){av.innerHTML='<img src="'+escapeHtml(avatar)+'" alt="">'}else{av.innerHTML=name.substring(0,2)}
 document.getElementById('chatListView').classList.remove('active');
 document.getElementById('chatThreadView').classList.add('active');
 document.getElementById('chatMessages').innerHTML='<div class="chat-widget-empty">Загрузка...</div>';
 loadMessages();
 if(pollTimer)clearInterval(pollTimer);
 pollTimer=setInterval(loadMessages,4000);
}

function backToList(){
 document.getElementById('chatThreadView').classList.remove('active');
 document.getElementById('chatListView').classList.add('active');
 if(pollTimer){clearInterval(pollTimer);pollTimer=null}
 currentLid=0;currentUid=0;
}

function loadMessages(){
 if(!currentLid||!currentUid)return;
 fetch('/api/messages?lid='+currentLid+'&uid='+currentUid)
 .then(function(r){return r.json()})
 .then(function(data){
  var box=document.getElementById('chatMessages');
  // Status
  var st=document.getElementById('threadStatus');
  if(data.other&&data.other.last_seen){
   var ls=new Date(data.other.last_seen.replace(/-/g,'/'));
   var diff=Math.floor((new Date()-ls)/1000);
   if(diff<120)st.className='chat-thread-status online';
   else st.className='chat-thread-status';
   if(diff<60)st.textContent='в сети';
   else if(diff<3600)st.textContent='был(а) '+Math.floor(diff/60)+' мин. назад';
   else if(diff<86400)st.textContent='был(а) '+Math.floor(diff/3600)+' ч. назад';
   else st.textContent='был(а) '+ls.toLocaleDateString('ru-RU');
  }
  // Typing
  document.getElementById('chatTyping').className='chat-typing'+(data.typing?' active':'');
  // Messages
  if(!data.messages||data.messages.length===0){box.innerHTML='<div class="chat-widget-empty">Нет сообщений</div>';return}
  var html='',lastDate='';
  for(var i=0;i<data.messages.length;i++){
   var m=data.messages[i];
   var d=new Date(m.created_at.replace(/-/g,'/'));
   var dateStr=d.toLocaleDateString('ru-RU',{day:'numeric',month:'long'});
   if(dateStr!==lastDate){html+='<div class="chat-date-sep">'+dateStr+'</div>';lastDate=dateStr}
   var mine=m.sender_id==myUid;
   var time=d.toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'});
   var statusHtml='';
   if(mine){
    if(m.is_read=='1')statusHtml='<svg width="12" height="12" viewBox="0 0 24 24" fill="#4ADE80"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/><path d="M17 16.2L12.8 12l-1.4 1.4L17 19l1.4-1.4z"/></svg>';
    else statusHtml='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="2"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>';
   }
   html+='<div class="chat-msg-group '+(mine?'mine':'theirs')+'">';
   if(!mine){html+='<div class="chat-msg-avatar">'+(otherAvatar?'<img src="'+escapeHtml(otherAvatar)+'" alt="">':escapeHtml(otherName.substring(0,2)))+'</div>'}
   html+='<div><div class="chat-msg-bubble">'+escapeHtml(m.text)+'</div><div class="chat-msg-meta"><span class="chat-msg-time">'+time+'</span><span class="chat-msg-status">'+statusHtml+'</span></div></div>';
   html+='</div>';
  }
  box.innerHTML=html;
  box.scrollTop=box.scrollHeight;
 }).catch(function(){});
}

function onTyping(){
 if(!currentLid||!currentUid)return;
 if(typingTimer)clearTimeout(typingTimer);
 typingTimer=setTimeout(function(){},500);
 fetch('/api/typing',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'lid='+currentLid}).catch(function(){});
}

function sendMessage(){
 var input=document.getElementById('chatInput'),text=input.value.trim();
 if(!text||!currentLid)return;
 input.value='';input.disabled=true;
 fetch('/api/send',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'lid='+currentLid+'&uid='+currentUid+'&text='+encodeURIComponent(text)})
 .then(function(r){return r.json()})
 .then(function(data){input.disabled=false;input.focus();if(data.ok)loadMessages();else alert('Ошибка отправки')})
 .catch(function(){input.disabled=false});
}

function escapeHtml(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}

document.addEventListener('click',function(e){
 if(!e.target.closest('.chat-fab-container')&&!e.target.closest('.chat-widget')&&!e.target.closest('.notif-panel')){
  document.getElementById('chatWidget').classList.remove('open');
  document.getElementById('notifPanel').classList.remove('open');
  if(document.getElementById('chatThreadView').classList.contains('active'))backToList();
 }
});
</script>
