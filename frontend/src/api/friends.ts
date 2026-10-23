import { api, post } from "./client";

export type Person = {
  id: number;
  first_name: string;
  last_name: string;
  relationship?: "none" | "incoming" | "outgoing" | "friend";
};

export type FriendRequest = {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  created_at: string;
};

export type FriendRequests = {
  incoming: FriendRequest[];
  outgoing: FriendRequest[];
};

export type FriendActivity = Person & {
  status: string;
  updated_at: string | null;
};

export function listFriends(): Promise<{ friends: Person[] }> {
  return api("/api/friends");
}

export function listFriendActivity(): Promise<{ activity: FriendActivity[] }> {
  return api("/api/friends/activity");
}

export function searchPeople(query: string): Promise<{ users: Person[] }> {
  return api(`/api/friends/search?q=${encodeURIComponent(query)}`);
}

export function listFriendRequests(): Promise<FriendRequests> {
  return api("/api/friend-requests");
}

export function sendFriendRequest(
  userId: number,
): Promise<{ message: string }> {
  return post("/api/friend-requests", { user_id: userId });
}

export function acceptFriendRequest(
  requestId: number,
): Promise<{ message: string }> {
  return post(`/api/friend-requests/${requestId}/accept`);
}

export function rejectFriendRequest(
  requestId: number,
): Promise<{ message: string }> {
  return post(`/api/friend-requests/${requestId}/reject`);
}

export function cancelFriendRequest(
  requestId: number,
): Promise<{ message: string }> {
  return api(`/api/friend-requests/${requestId}`, { method: "DELETE" });
}

export function removeFriend(userId: number): Promise<{ message: string }> {
  return api(`/api/friends/${userId}`, { method: "DELETE" });
}
