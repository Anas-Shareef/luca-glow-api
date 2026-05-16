<x-mail::message>
# Hi {{ $name }},

Welcome to the **Luca Glow** team! You have been invited to join our administrative panel as a **{{ $role }}**.

@if($password)
We are excited to have you on board to help us redefine beauty and clean skincare. Below are your temporary login credentials to get you started:

<x-mail::panel>
**Email:** {{ $email }}  
**Password:** `{{ $password }}`
</x-mail::panel>
@else
We are excited to have you on board! Since you already have an account with us, you can log in using your existing credentials.
@endif

<x-mail::button :url="$url" color="success">
Access Admin Panel
</x-mail::button>

@if($password)
> [!IMPORTANT]
> For security reasons, please change your password immediately after your first login in the account settings section.
@endif

If you have any questions or need assistance setting up your workspace, please reach out to the system administrator.

Stay Glowing,<br>
**The Luca Glow Team**
</x-mail::message>
