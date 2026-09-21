import { useState, useEffect } from 'react';
import { chatService } from '../api/chatService';
import { useChatStore } from '../stores/chatStore';
import { toast } from 'react-hot-toast';
import { Send, User } from 'lucide-react';

export const ChatPage = () => {
  const {
    chats,
    setChats,
    messages,
    setMessages,
    addMessage,
  } = useChatStore();

  const [selectedChat, setSelectedChat] = useState<any>(null);
  const [newMessage, setNewMessage] = useState('');

  useEffect(() => {
    chatService
        .getChats()
        .then(setChats)
        .catch(() => toast.error('Failed to load chats'));
  }, [setChats]);

  useEffect(() => {
    if (selectedChat) {
      chatService
          .getMessages(selectedChat.id)
          .then(setMessages)
          .catch(() => toast.error('Failed to load messages'));
    }
  }, [selectedChat, setMessages]);

  const handleSendMessage = async () => {
    if (!newMessage.trim() || !selectedChat) return;

    try {
      const message = await chatService.sendMessage(
          selectedChat.id,
          newMessage
      );

      addMessage(message);
      setNewMessage('');
    } catch {
      toast.error('Failed to send message');
    }
  };

  return (
      <div
          className="
        flex
        h-[calc(100vh-100px)]
        bg-white
        dark:bg-gray-900
        rounded-xl
        shadow
        dark:shadow-black/30
        border
        border-gray-200
        dark:border-gray-800
        overflow-hidden
        transition-colors
        duration-300
      "
      >
        {/* Chat List */}
        <div
            className="
          w-1/3
          border-r
          border-gray-200
          dark:border-gray-800
          overflow-y-auto
          bg-white
          dark:bg-gray-900
          transition-colors
          duration-300
        "
        >
          <h2
              className="
            text-xl
            font-bold
            p-4
            border-b
            border-gray-200
            dark:border-gray-800
            text-gray-900
            dark:text-white
            transition-colors
          "
          >
            Messages
          </h2>

          {chats.length > 0 ? (
              chats.map((chat: any) => (
                  <div
                      key={chat.id}
                      className={`
                p-4
                cursor-pointer
                border-b
                border-gray-100
                dark:border-gray-800
                transition-colors
                duration-200
                ${
                          selectedChat?.id === chat.id
                              ? 'bg-gray-200 dark:bg-gray-800'
                              : 'hover:bg-gray-100 dark:hover:bg-gray-800/60'
                      }
              `}
                      onClick={() => setSelectedChat(chat)}
                  >
                    <div className="flex items-center gap-3">
                      <div
                          className="
                    w-10
                    h-10
                    rounded-full
                    bg-gray-100
                    dark:bg-gray-800
                    text-gray-600
                    dark:text-gray-300
                    flex
                    items-center
                    justify-center
                    flex-shrink-0
                  "
                      >
                        <User size={18} />
                      </div>

                      <div className="min-w-0">
                        <p
                            className="
                      font-semibold
                      text-gray-900
                      dark:text-white
                      truncate
                    "
                        >
                          {chat.recipientName}
                        </p>
                      </div>
                    </div>
                  </div>
              ))
          ) : (
              <div
                  className="
              p-6
              text-center
              text-gray-500
              dark:text-gray-400
            "
              >
                No conversations yet
              </div>
          )}
        </div>

        {/* Chat Area */}
        <div
            className="
          w-2/3
          flex
          flex-col
          bg-gray-50
          dark:bg-gray-950
          transition-colors
          duration-300
        "
        >
          {selectedChat ? (
              <>
                {/* Chat Header */}
                <div
                    className="
                p-4
                border-b
                border-gray-200
                dark:border-gray-800
                bg-white
                dark:bg-gray-900
                font-bold
                text-gray-900
                dark:text-white
                transition-colors
                duration-300
              "
                >
                  {selectedChat.recipientName}
                </div>

                {/* Messages */}
                <div
                    className="
                flex-1
                overflow-y-auto
                p-4
                space-y-4
              "
                >
                  {messages.length > 0 ? (
                      messages.map((msg: any) => {
                        const isMine = msg.senderId === 'me';

                        return (
                            <div
                                key={msg.id}
                                className={`
                        flex
                        ${isMine ? 'justify-end' : 'justify-start'}
                      `}
                            >
                              <div
                                  className={`
                          max-w-[75%]
                          p-3
                          rounded-xl
                          break-words
                          ${
                                      isMine
                                          ? 'bg-black dark:bg-white text-white dark:text-black'
                                          : 'bg-gray-200 dark:bg-gray-800 text-gray-900 dark:text-gray-100'
                                  }
                        `}
                              >
                                {msg.content}
                              </div>
                            </div>
                        );
                      })
                  ) : (
                      <div
                          className="
                    h-full
                    flex
                    items-center
                    justify-center
                    text-gray-500
                    dark:text-gray-400
                  "
                      >
                        No messages yet
                      </div>
                  )}
                </div>

                {/* Message Input */}
                <div
                    className="
                p-4
                border-t
                border-gray-200
                dark:border-gray-800
                bg-white
                dark:bg-gray-900
                flex
                gap-2
                transition-colors
                duration-300
              "
                >
                  <input
                      value={newMessage}
                      onChange={(e) => setNewMessage(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          handleSendMessage();
                        }
                      }}
                      className="
                  flex-1
                  border
                  border-gray-300
                  dark:border-gray-700
                  bg-white
                  dark:bg-gray-800
                  text-gray-900
                  dark:text-white
                  p-2.5
                  rounded-lg
                  outline-none
                  placeholder-gray-400
                  dark:placeholder-gray-500
                  focus:ring-2
                  focus:ring-gray-300
                  dark:focus:ring-gray-600
                  transition-colors
                "
                      placeholder="Type a message..."
                  />

                  <button
                      type="button"
                      onClick={handleSendMessage}
                      disabled={!newMessage.trim()}
                      className="
                  bg-black
                  dark:bg-white
                  text-white
                  dark:text-black
                  p-2.5
                  rounded-lg
                  flex
                  items-center
                  justify-center
                  hover:bg-gray-800
                  dark:hover:bg-gray-200
                  disabled:opacity-40
                  disabled:cursor-not-allowed
                  transition-colors
                "
                      aria-label="Send message"
                  >
                    <Send size={18} />
                  </button>
                </div>
              </>
          ) : (
              <div
                  className="
              flex-1
              flex
              flex-col
              items-center
              justify-center
              text-gray-500
              dark:text-gray-400
              px-6
              text-center
            "
              >
                <div
                    className="
                w-16
                h-16
                rounded-full
                bg-gray-200
                dark:bg-gray-800
                flex
                items-center
                justify-center
                mb-4
              "
                >
                  <Send
                      size={26}
                      className="text-gray-500 dark:text-gray-400"
                  />
                </div>

                <p className="text-gray-600 dark:text-gray-300 font-medium">
                  Select a chat to start messaging
                </p>

                <p className="text-sm text-gray-400 dark:text-gray-500 mt-1">
                  Your conversations will appear here
                </p>
              </div>
          )}
        </div>
      </div>
  );
};