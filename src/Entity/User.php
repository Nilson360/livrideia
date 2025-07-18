<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà utilisé.')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $birthday = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $posts;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $likes;

    #[ORM\OneToMany(targetEntity: Share::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $shares;

    #[ORM\OneToMany(targetEntity: Friend::class, mappedBy: 'receiver', cascade: ['persist', 'remove'])]
    private Collection $receivedFriendRequests;

    #[ORM\Column]
    private bool $isVerified = false;
    #[ORM\OneToMany(targetEntity: Friend::class, mappedBy: 'sender', cascade: ['persist', 'remove'])]
    private Collection $sentFriendRequests;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    /**
     * @var Collection<int, ChatRoom>
     */
    #[ORM\OneToMany(targetEntity: ChatRoom::class, mappedBy: 'user1')]
    private Collection $chatRooms;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'sender')]
    private Collection $messages;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isSubscribedToNewsletter = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverPath = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActivity = null;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->shares = new ArrayCollection();
        $this->isVerified = true;
        $this->receivedFriendRequests = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
        $this->sentFriendRequests = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->chatRooms = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Clear temporary, sensitive data
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(\DateTimeInterface $birthday): static
    {
        $this->birthday = $birthday;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    // Gestion des relations avec Post
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setUser($this);
        }
        return $this;
    }

    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getUser() === $this) {
                $post->setUser(null);
            }
        }
        return $this;
    }

    // Gestion des relations avec Comment
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setUser($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getUser() === $this) {
                $comment->setUser(null);
            }
        }
        return $this;
    }

    // Gestion des relations avec Like
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(Like $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setUser($this);
        }
        return $this;
    }

    public function removeLike(Like $like): static
    {
        if ($this->likes->removeElement($like)) {
            if ($like->getUser() === $this) {
                $like->setUser(null);
            }
        }
        return $this;
    }


    // Gestion des relations avec Share
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function addShare(Share $share): static
    {
        if (!$this->shares->contains($share)) {
            $this->shares->add($share);
            $share->setUser($this);
        }
        return $this;
    }

    public function removeShare(Share $share): static
    {
        if ($this->shares->removeElement($share)) {
            if ($share->getUser() === $this) {
                $share->setUser(null);
            }
        }
        return $this;
    }

    // Gestion des relations avec Friend (receivedFriendRequests)
    public function getReceivedFriendRequests(): Collection
    {
        return $this->receivedFriendRequests;
    }

    public function addReceivedFriendRequest(Friend $receivedFriendRequest): static
    {
        if (!$this->receivedFriendRequests->contains($receivedFriendRequest)) {
            $this->receivedFriendRequests->add($receivedFriendRequest);
            $receivedFriendRequest->setReceiver($this);
        }
        return $this;
    }

    public function removeReceivedFriendRequest(Friend $receivedFriendRequest): static
    {
        if ($this->receivedFriendRequests->removeElement($receivedFriendRequest)) {
            if ($receivedFriendRequest->getReceiver() === $this) {
                $receivedFriendRequest->setReceiver(null);
            }
        }
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getSentFriendRequests(): Collection
    {
        return $this->sentFriendRequests;
    }

    public function addSentFriendRequest(Friend $friend): static
    {
        if (!$this->sentFriendRequests->contains($friend)) {
            $this->sentFriendRequests->add($friend);
            $friend->setSender($this);
        }
        return $this;
    }

    public function removeSentFriendRequest(Friend $friend): static
    {
        if ($this->sentFriendRequests->removeElement($friend)) {
            if ($friend->getSender() === $this) {
                $friend->setSender(null);
            }
        }
        return $this;
    }

    public function isFriendsWith(User $user): bool
    {
        foreach ($this->sentFriendRequests as $friendRequest) {
            if ($friendRequest->getReceiver() === $user && $friendRequest->getStatus() === 'accepted') {
                return true;
            }
        }

        foreach ($this->receivedFriendRequests as $friendRequest) {
            if ($friendRequest->getSender() === $user && $friendRequest->getStatus() === 'accepted') {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setRecipient($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getRecipient() === $this) {
                $notification->setRecipient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChatRoom>
     */
    public function getChatRooms(): Collection
    {
        return $this->chatRooms;
    }

    public function addChatRoom(ChatRoom $chatRoom): static
    {
        if (!$this->chatRooms->contains($chatRoom)) {
            $this->chatRooms->add($chatRoom);
            $chatRoom->setUser1($this);
        }

        return $this;
    }

    public function removeChatRoom(ChatRoom $chatRoom): static
    {
        if ($this->chatRooms->removeElement($chatRoom)) {
            // set the owning side to null (unless already changed)
            if ($chatRoom->getUser1() === $this) {
                $chatRoom->setUser1(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setSender($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }

        return $this;
    }

    public function isSubscribedToNewsletter(): bool
    {
        return $this->isSubscribedToNewsletter;
    }

    public function setIsSubscribedToNewsletter(bool $isSubscribedToNewsletter): self
    {
        $this->isSubscribedToNewsletter = $isSubscribedToNewsletter;

        return $this;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): self
    {
        $this->avatarPath = $avatarPath;
        return $this;
    }

    public function getCoverPath(): ?string
    {
        return $this->coverPath;
    }

    public function setCoverPath(?string $coverPath): self
    {
        $this->coverPath = $coverPath;
        return $this;
    }

    public function getLastActivity(): ?\DateTimeImmutable
    {
        return $this->lastActivity;
    }

    public function setLastActivity(?\DateTimeImmutable $lastActivity): static
    {
        $this->lastActivity = $lastActivity;


        return $this;
    }

    public function updateLastActivity(): self
    {
        $this->lastActivity = new \DateTimeImmutable();
        return $this;
    }
    /**
     * Compter le nombre d'amis acceptés
     */
    public function getFriendsCount(): int
    {
        $count = 0;

        // Compter les demandes envoyées et acceptées
        foreach ($this->sentFriendRequests as $request) {
            if ($request->getStatus() === 'accepted') {
                $count++;
            }
        }

        // Compter les demandes reçues et acceptées
        foreach ($this->receivedFriendRequests as $request) {
            if ($request->getStatus() === 'accepted') {
                $count++;
            }
        }

        return $count;
    }
}
