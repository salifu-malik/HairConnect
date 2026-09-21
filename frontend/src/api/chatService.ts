import axios from '../api/axios';

export const chatService = {
  getChats: () => axios.get('/chat/chats').then(res => res.data),
  getMessages: (chatId: string) => axios.get(`/chat/messages/${chatId}`).then(res => res.data),
  sendMessage: (chatId: string, content: string) => axios.post(`/chat/messages/${chatId}`, { content }).then(res => res.data),
  createChat: (recipientId: string) => axios.post('/chat/chats', { recipientId }).then(res => res.data),
};
