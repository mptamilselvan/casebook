import 'package:material_ui/material_ui.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../api.dart';
import '../models.dart';
import '../theme.dart';

class PlanOffer {
  PlanOffer({
    required this.id,
    required this.name,
    required this.spaceLabel,
    required this.casesLabel,
    required this.priceLabel,
    required this.current,
  });

  final int id;
  final String name;
  final String spaceLabel;
  final String casesLabel;
  final String priceLabel;
  final bool current;

  static List<PlanOffer> listFromJson(Map<String, dynamic> json) {
    final currentId = json['current_plan_id'];
    final plans = json['plans'] as List? ?? [];
    return [
      for (final item in plans)
        if (item is Map<String, dynamic> || item is Map)
          PlanOffer.fromJson(Map<String, dynamic>.from(item), currentId),
    ];
  }

  factory PlanOffer.fromJson(Map<String, dynamic> json, Object? currentId) {
    final price = json['price'] as Map?;
    return PlanOffer(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      spaceLabel: json['space_label'] as String? ?? '',
      casesLabel: json['cases_label'] as String? ?? '',
      priceLabel: price?['label'] as String? ?? '',
      current:
          currentId is num && currentId.toInt() == (json['id'] as num).toInt(),
    );
  }
}

class PricesPage extends StatefulWidget {
  const PricesPage({super.key, required this.api, this.onUser});

  final ApiClient api;
  final ValueChanged<UserAccount>? onUser;

  @override
  State<PricesPage> createState() => _PricesPageState();
}

class _PricesPageState extends State<PricesPage> {
  List<PlanOffer> plans = const [];
  String? error;
  String? notice;
  bool loading = true;
  int? applyingId;
  Razorpay? _razorpay;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _razorpay?.clear();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final body = await widget.api.getJson('plans');
      if (!mounted) {
        return;
      }
      setState(() {
        plans = PlanOffer.listFromJson(body as Map<String, dynamic>);
        loading = false;
        error = null;
      });
    } catch (failure) {
      if (!mounted) {
        return;
      }
      setState(() {
        loading = false;
        error = failure.toString();
      });
    }
  }

  Future<void> _apply(PlanOffer plan) async {
    setState(() {
      applyingId = plan.id;
      error = null;
      notice = null;
    });
    try {
      final body = await widget.api.postJson('plans/${plan.id}/checkout', {});
      if (body is! Map) {
        throw ApiException('Payment could not be started.');
      }
      final payload = Map<String, dynamic>.from(body);
      if (payload['status'] == 'paid') {
        await _finish(payload);
        return;
      }
      _openCheckout(payload);
    } catch (failure) {
      if (!mounted) {
        return;
      }
      setState(() {
        applyingId = null;
        error = failure.toString();
      });
    }
  }

  void _openCheckout(Map<String, dynamic> body) {
    _razorpay?.clear();
    final razorpay = Razorpay();
    razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, (
      PaymentSuccessResponse response,
    ) async {
      try {
        final verified = await widget.api.postJson('plans/verify', {
          'razorpay_order_id': response.orderId,
          'razorpay_payment_id': response.paymentId,
          'razorpay_signature': response.signature,
        });
        if (verified is Map) {
          await _finish(Map<String, dynamic>.from(verified));
        }
      } catch (failure) {
        if (!mounted) {
          return;
        }
        setState(() {
          applyingId = null;
          error = failure.toString();
        });
      }
    });
    razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, (
      PaymentFailureResponse response,
    ) {
      if (!mounted) {
        return;
      }
      setState(() {
        applyingId = null;
        error = response.message ?? 'Payment was not completed.';
      });
    });
    razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (
      ExternalWalletResponse response,
    ) {
      if (!mounted) {
        return;
      }
      setState(() => applyingId = null);
    });
    razorpay.open({
      'key': body['key'],
      'amount': body['amount'],
      'currency': body['currency'] ?? 'INR',
      'name': body['name'] ?? 'Case Book',
      'description': body['description'] ?? '',
      'order_id': body['order_id'],
      'prefill': {
        'email': body['email'] ?? '',
        'name': body['customer_name'] ?? '',
      },
      'theme': {'color': '#2563EB'},
    });
    _razorpay = razorpay;
  }

  Future<void> _finish(Map<String, dynamic> body) async {
    final user = body['user'];
    if (user is Map) {
      widget.onUser?.call(
        UserAccount.fromJson(Map<String, dynamic>.from(user)),
      );
    }
    final name = body['plan_name'] as String? ?? 'This plan';
    await _load();
    if (!mounted) {
      return;
    }
    setState(() {
      applyingId = null;
      notice = '$name is now your plan.';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(title: const Text('Prices')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : plans.isEmpty && error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  error!,
                  style: const TextStyle(color: casebookDanger),
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              children: [
                const Text(
                  'Prices are in Indian rupees (INR).',
                  style: TextStyle(color: casebookMuted, fontSize: 13),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Apply opens Razorpay. You can pay by UPI, QR code, net banking, or debit and credit card.',
                  style: TextStyle(color: casebookMuted, fontSize: 13),
                ),
                if (notice != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    notice!,
                    style: const TextStyle(color: Color(0xFF047857)),
                  ),
                ],
                if (error != null) ...[
                  const SizedBox(height: 12),
                  Text(error!, style: const TextStyle(color: casebookDanger)),
                ],
                const SizedBox(height: 16),
                for (final plan in plans) ...[
                  _PlanCard(
                    plan: plan,
                    applying: applyingId == plan.id,
                    onApply: applyingId == null ? () => _apply(plan) : null,
                  ),
                  const SizedBox(height: 12),
                ],
              ],
            ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.plan, required this.applying, this.onApply});

  final PlanOffer plan;
  final bool applying;
  final VoidCallback? onApply;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: plan.current ? const Color(0xFFEFF6FF) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: plan.current ? casebookBlue : casebookLine),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  plan.name,
                  style: const TextStyle(
                    color: casebookText,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (plan.current)
                const Text(
                  'Your plan',
                  style: TextStyle(
                    color: casebookBlue,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: plan.current ? Colors.white : const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: plan.current ? const Color(0xFFBFDBFE) : casebookLine,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'INR',
                  style: TextStyle(
                    color: casebookMuted,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.2,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  plan.priceLabel,
                  style: const TextStyle(
                    color: casebookText,
                    fontSize: 32,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Text(plan.spaceLabel, style: const TextStyle(color: casebookMuted)),
          const SizedBox(height: 4),
          Text(plan.casesLabel, style: const TextStyle(color: casebookMuted)),
          if (!plan.current) ...[
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: onApply,
                child: Text(
                  applying ? 'Please wait' : 'Apply · ${plan.priceLabel}',
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
