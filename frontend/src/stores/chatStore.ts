import { create } from 'zustand';

interface ChatStore {
  chats: any[];
  messages: any[];
  setChats: (chats: any[]) => void;
  setMessages: (messages: any[]) => void;
  addMessage: (message: any) => void;
}

export const useChatStore = create<ChatStore>((set) => ({
  chats: [],
  messages: [],
  setChats: (chats) => set({ chats }),
  setMessages: (messages) => set({ messages }),
  addMessage: (message) => set((state) => ({ messages: [...state.messages, message] })),
}));
